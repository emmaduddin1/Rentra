<?php

/** Destructive ONLY to an explicitly isolated audit/CI database. Never uses .env. */
declare(strict_types=1);

use App\Actions\MonthlyRecords\RecordMonthlyPayment;
use App\Actions\MonthlyRecords\UpdateMonthlyRecord;
use App\Actions\Tenancies\AssignRenter;
use App\Actions\Tenancies\EndTenancy;
use App\Actions\Tenancies\RequestTenancyTermination;
use App\Actions\Tenancies\RespondToInvitation;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$database = getenv('TENANTISM_AUDIT_MYSQL_DATABASE');
$socket = getenv('TENANTISM_AUDIT_MYSQL_SOCKET') ?: '';
$ci = getenv('GITHUB_ACTIONS') === 'true' && $database === 'tenantism_ci_test';
if (! $ci && ($database !== 'tenantism_audit_stabilization' || ! str_starts_with($socket, '/private/tmp/tenantism-audit-mysql-isolated-'))) {
    fwrite(STDERR, "Refusing to use any database except the dedicated audit socket or CI service.\n");
    exit(1);
}
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_DATABASE' => $database, 'DB_SOCKET' => $socket, 'DB_HOST' => '127.0.0.1', 'DB_PORT' => getenv('TENANTISM_AUDIT_MYSQL_PORT') ?: '3306', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => getenv('TENANTISM_AUDIT_MYSQL_PASSWORD') ?: '', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'PUSH_NOTIFICATIONS_ENABLED' => 'false', 'FIREBASE_CREDENTIALS' => '', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/tenantism-no-config-cache.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDatabaseName() !== $database || DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('Unexpected database connection.');
}
if (! $ci && rtrim(DB::selectOne('SELECT @@socket AS socket')->socket, '/') !== $socket) {
    throw new RuntimeException('Unexpected MySQL server socket.');
}

if (($argv[1] ?? '') === 'worker') {
    [$mode, $id, $ready, $receiptKey] = array_slice($argv, 2);
    $model = $mode === 'receipt' ? MonthlyRecord::findOrFail($id) : Tenancy::findOrFail($id);
    // Load the old model before the parent commits a conflicting operation.
    touch($ready);
    if ($mode === 'receipt') {
        app(RecordMonthlyPayment::class)->handle($model, $model->tenancy->property->owner, ['amount' => '10000.00', 'received_on' => today()->toDateString(), 'idempotency_key' => $receiptKey]);
    } elseif ($mode === 'accept') {
        try {
            app(RespondToInvitation::class)->accept($model, $model->renter);
        } catch (ValidationException) {
            // One competing invitation must lose to the accepted contract.
        }
    } else {
        try {
            app(RequestTenancyTermination::class)->handle($model, $model->renter, ['proposed_end_date' => today()->addDays(10)->toDateString(), 'reason' => 'Concurrent request']);
            throw new RuntimeException('Ended tenancy was reactivated.');
        } catch (ValidationException) {
            // Correct: the locked, current state rejects this stale request.
        }
    }
    echo "worker passed\n";
    exit;
}

Artisan::call('migrate:fresh', ['--force' => true]);
$directory = sys_get_temp_dir().'/tenantism-concurrency-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$children = [];
$spawn = function (string $mode, int $id, string $key) use (&$children, $directory): void {
    $ready = $directory.'/ready-'.count($children);
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $mode, (string) $id, $ready, $key], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start concurrency worker.');
    }
    fclose($pipes[0]);
    $children[] = [$process, $pipes, $ready];
    $deadline = microtime(true) + 15;
    while (! file_exists($ready) && microtime(true) < $deadline) {
        usleep(20000);
    }
    if (! file_exists($ready)) {
        throw new RuntimeException('Worker failed to reach the barrier.');
    }
    usleep(150000);
    if (! proc_get_status($process)['running']) {
        throw new RuntimeException('Worker did not wait on the parent row lock.');
    }
};
$join = function () use (&$children): void {
    while ($child = array_shift($children)) {
        [$process, $pipes, $ready] = $child;
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        unlink($ready);
        if ($exit !== 0 || ! str_contains($output, 'worker passed')) {
            throw new RuntimeException('Concurrency worker failed: '.$output);
        }
    }
    $children = [];
};
try {
    $record = MonthlyRecord::factory()->create(['rent_amount' => '50000.00', 'electricity_amount' => 0, 'gas_amount' => 0]);
    $key = (string) Str::uuid();
    DB::beginTransaction();
    $record->refreshForUpdate();
    $spawn('receipt', $record->id, $key);
    $spawn('receipt', $record->id, $key);
    app(UpdateMonthlyRecord::class)->handle($record, ['rent_amount' => '60000.00']);
    DB::commit();
    $join();
    $record->refresh();
    if ($record->payments()->count() !== 1 || $record->total_receivable !== '60000.00' || $record->total_received !== '10000.00' || $record->outstanding_receivable !== '50000.00') {
        throw new RuntimeException('Concurrent receipt deduplication or aggregate failed.');
    }
    $tenancy = Tenancy::factory()->create();
    DB::beginTransaction();
    $tenancy->refreshForUpdate();
    $spawn('termination', $tenancy->id, '-');
    app(EndTenancy::class)->handle($tenancy, $tenancy->property->owner, today());
    DB::commit();
    $join();
    if ($tenancy->fresh()->isActive() || $tenancy->terminationRequests()->exists()) {
        throw new RuntimeException('Concurrent lifecycle history failed.');
    }
    foreach (['same renter', 'same property'] as $scenario) {
        $owner = User::factory()->owner()->create();
        $renter = User::factory()->renter()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $otherProperty = $scenario === 'same property' ? $property : Property::factory()->for($owner, 'owner')->create();
        $otherRenter = $scenario === 'same renter' ? $renter : User::factory()->renter()->create();
        $terms = ['start_date' => today()->toDateString(), 'agreed_rent' => '50000.00'];
        $first = app(AssignRenter::class)->handle($owner, $property, $renter, $terms);
        $second = app(AssignRenter::class)->handle($owner, $otherProperty, $otherRenter, $terms);
        DB::beginTransaction();
        if ($scenario === 'same renter') {
            User::whereKey($renter->id)->lockForUpdate()->firstOrFail();
        } else {
            Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
        }
        $spawn('accept', $first->id, '-');
        $spawn('accept', $second->id, '-');
        DB::commit();
        $join();
        $ids = [$first->id, $second->id];
        if (Tenancy::whereIn('id', $ids)->active()->count() !== 1 || DB::table('tenancy_active_locks')->whereIn('tenancy_id', $ids)->count() !== 1) {
            throw new RuntimeException('Competing acceptances failed: '.$scenario);
        }
    }
    echo json_encode(['result' => 'passed', 'database' => $database, 'server' => DB::selectOne('SELECT VERSION() AS version')->version, 'scenarios' => ['two simultaneous keyed receipts vs amount update', 'termination request vs completed end', 'competing invitation acceptances for one renter', 'competing invitation acceptances for one property'], 'workers' => 7], JSON_PRETTY_PRINT)."\n";
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($children as [$process, $pipes, $ready]) {
        if (is_resource($process)) {
            proc_terminate($process);
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($process)) {
            proc_close($process);
        }
        @unlink($ready);
    }
    rmdir($directory);
}
