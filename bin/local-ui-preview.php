<?php

/** Isolated local UI preview. Never reads the application's configured database. */
declare(strict_types=1);
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Favorite;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

$projectRoot = dirname(__DIR__);
$previewRoot = $projectRoot.'/storage/app/private/local-ui-preview';
$previewDatabase = $previewRoot.'/preview.sqlite';
foreach (['', '/sessions', '/views', '/uploads', '/private-uploads'] as $directory) {
    if (! is_dir($previewRoot.$directory)) {
        mkdir($previewRoot.$directory, 0700, true);
    }
}
if (! is_file($previewRoot.'/app-key')) {
    file_put_contents($previewRoot.'/app-key', 'base64:'.base64_encode(random_bytes(32)));
    chmod($previewRoot.'/app-key', 0600);
}
foreach ([
    'APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'APP_URL' => 'http://127.0.0.1:8000',
    'APP_KEY' => trim(file_get_contents($previewRoot.'/app-key')), 'ASSET_URL' => 'http://127.0.0.1:8000',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $previewDatabase, 'DB_URL' => '',
    'SESSION_DRIVER' => 'file', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
    'SESSION_COOKIE' => 'tenantism_local_ui_preview', 'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null',
    'LOG_CHANNEL' => 'single', 'LOG_STACK' => 'single',
    'PUSH_NOTIFICATIONS_ENABLED' => 'false', 'PUSH_ENVIRONMENT' => 'local',
    'FIREBASE_CREDENTIALS' => '', 'FIREBASE_PROJECT_ID' => '',
    'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
    'APP_CONFIG_CACHE' => $previewRoot.'/config.php', 'APP_ROUTES_CACHE' => $previewRoot.'/routes.php',
    'VIEW_COMPILED_PATH' => $previewRoot.'/views',
    'RENTAL_PUBLIC_DISK' => 'local-ui-preview', 'RENTAL_PRIVATE_DISK' => 'local-ui-preview-private',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require $projectRoot.'/vendor/autoload.php';

function localUiPreviewApp(): Application
{
    global $projectRoot, $previewRoot, $previewDatabase;
    $app = require $projectRoot.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    config([
        'session.files' => $previewRoot.'/sessions',
        'filesystems.disks.local-ui-preview' => ['driver' => 'local', 'root' => $previewRoot.'/uploads', 'url' => '/__local-preview-media', 'throw' => true],
        'filesystems.disks.local-ui-preview-private' => ['driver' => 'local', 'root' => $previewRoot.'/private-uploads', 'throw' => true],
        'filesystems.disks.public' => ['driver' => 'local', 'root' => $previewRoot.'/uploads', 'url' => '/__local-preview-media'],
        'filesystems.disks.local' => ['driver' => 'local', 'root' => $previewRoot.'/private-uploads'],
    ]);
    if (! app()->environment('local') || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== $previewDatabase
        || config('push.enabled') || config('mail.default') !== 'array') {
        throw new RuntimeException('Local preview isolation check failed.');
    }

    return $app;
}

if (PHP_SAPI === 'cli-server') {
    if (! is_file($previewRoot.'/ready')) {
        http_response_code(503);
        exit('Prepare the isolated database first: php bin/local-ui-preview.php prepare');
    }
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (str_starts_with($path, '/__local-preview-media/')) {
        $file = realpath($previewRoot.'/uploads/'.substr($path, strlen('/__local-preview-media/')));
        if (! $file || ! str_starts_with($file, $previewRoot.'/uploads/') || ! is_file($file)
            || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm'], true)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: '.mime_content_type($file));
        readfile($file);

        return;
    }
    $static = realpath($projectRoot.'/public'.$path);
    if ($path !== '/' && $static && str_starts_with($static, $projectRoot.'/public/')
        && is_file($static) && strtolower(pathinfo($static, PATHINFO_EXTENSION)) !== 'php') {
        return false;
    }
    $app = localUiPreviewApp();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
        $notice = '<aside aria-label="Local preview" style="position:fixed;bottom:8px;left:8px;z-index:90;max-width:calc(100vw - 16px);border:1px solid white;border-radius:8px;background:#1a273a;color:white;padding:6px 10px;font:11px system-ui;pointer-events:none">LOCAL PREVIEW · sample data · delivery disabled</aside>';
        $response->setContent(str_replace('</body>', $notice.'</body>', $response->getContent()));
    }
    $response->send();
    $kernel->terminate($request, $response);

    return;
}

$command = $argv[1] ?? 'serve';
if ($command === 'prepare') {
    if (is_file($previewRoot.'/ready')) {
        echo "Local preview is already prepared; existing sample data was retained.\n";
        exit;
    }
    if (file_exists($previewDatabase)) {
        throw new RuntimeException('An unfinished preview database already exists. It will not be reset or overwritten.');
    }
    touch($previewDatabase);
    chmod($previewDatabase, 0600);
    localUiPreviewApp();
    // This command only migrates a newly created, dedicated SQLite database.
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('The new isolated SQLite database could not be prepared. No existing database was reset.');
    }
    $password = 'Tenantism-local-2026';
    $accounts = [];
    foreach (['owner' => 'Ayesha Khan', 'renter' => 'Hamza Ahmed', 'empty-owner' => 'New owner', 'empty-renter' => 'New renter'] as $role => $name) {
        $factory = str_contains($role, 'owner') ? User::factory()->owner() : User::factory()->renter();
        $accounts[$role] = $factory->create(['name' => $name, 'email' => $role.'@tenantism.test', 'password' => Hash::make($password), 'product_tour_completed_at' => now()]);
    }
    $owner = $accounts['owner'];
    $renter = $accounts['renter'];
    $category = PropertyCategory::where('slug', 'house')->firstOrFail();
    copy($projectRoot.'/public/images/tenantism-hero.webp', $previewRoot.'/uploads/sample-home.webp');
    $properties = [];
    foreach (['Garden residence, DHA', 'Bright family home with a private terrace and a spacious living area in Gulberg', 'Clifton courtyard home', 'Quiet home in Model Town'] as $index => $title) {
        $property = Property::factory()->published()->create([
            'owner_id' => $owner->id, 'category_id' => $category->id, 'type' => PropertyType::House,
            'title' => $title, 'area' => 'Gulberg', 'city' => 'Lahore', 'address' => '12 Garden Lane',
            'description' => 'Local preview listing. A bright family home with generous living spaces and a practical layout.',
            'rent_amount' => 85000 + $index * 5000, 'bedrooms' => 3, 'bathrooms' => 2,
        ]);
        if ($index !== 3) {
            PropertyImage::factory()->primary()->create(['property_id' => $property->id, 'disk' => 'local-ui-preview', 'file_path' => 'sample-home.webp']);
        }
        $properties[] = $property;
    }
    $properties[0]->update(['status' => PropertyStatus::Occupied]);
    $tenancy = Tenancy::factory()->create(['property_id' => $properties[0]->id, 'renter_id' => $renter->id, 'agreed_rent' => 85000, 'deposit' => 170000]);
    foreach ([0, 1] as $monthsAgo) {
        $factory = MonthlyRecord::factory()->for($tenancy)->state([
            'period_month' => now()->subMonths($monthsAgo)->startOfMonth()->toDateString(),
            'rent_amount' => 85000, 'electricity_amount' => 0, 'gas_amount' => 0, 'direct_utility_bills' => 0,
            'total_amount' => 85000, 'total_receivable' => 85000, 'total_received' => 0, 'outstanding_receivable' => 85000,
        ]);
        $monthsAgo ? $factory->approved()->create(['approved_by' => $owner->id]) : $factory->pending()->create();
    }
    MaintenanceRequest::factory()->for($tenancy)->create(['title' => 'Kitchen tap needs a repair', 'description' => 'Local preview request: the kitchen tap has a slow drip.']);
    App\Models\Application::factory()->create(['property_id' => $properties[1]->id, 'renter_id' => $renter->id]);
    App\Models\Application::factory()->create(['property_id' => $properties[2]->id, 'renter_id' => $accounts['empty-renter']->id]);
    Favorite::create(['renter_id' => $renter->id, 'property_id' => $properties[1]->id]);
    foreach ([$owner, $renter] as $user) {
        $user->inAppNotifications()->create(['type' => 'account.local_preview', 'title' => 'Welcome to your local workspace', 'body' => 'Sample notification for reviewing the dashboard. No desktop notification was sent.']);
    }
    file_put_contents($previewRoot.'/ready', 'Local-only sample database prepared on '.date(DATE_ATOM));
    echo "Isolated local preview prepared. No shared database or delivery service was used.\n";
    echo "Owner: owner@tenantism.test | Renter: renter@tenantism.test\nPassword for sample accounts: {$password}\n";
    exit;
}
if ($command !== 'serve') {
    fwrite(STDERR, "Usage: php bin/local-ui-preview.php [prepare|serve]\n");
    exit(1);
}
if (! is_file($previewRoot.'/ready')) {
    fwrite(STDERR, "First run: php bin/local-ui-preview.php prepare\n");
    exit(1);
}
echo "Tenantism local preview: http://127.0.0.1:8000\nSample data only. Email and push delivery are disabled. Press Ctrl+C to stop.\n";
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:8000', '-t', $projectRoot.'/public', __FILE__], $projectRoot);
$server->setTimeout(null);
$server->setTty(Process::isTtySupported());
exit($server->run(function ($type, $buffer) {
    echo $buffer;
}));
