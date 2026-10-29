<?php

use Illuminate\Contracts\Console\Kernel;

// CLI-only Hostinger entry point: one locked scheduler + web-push worker invocation.
$root = '/home/u577461158/domains/staging.tenantism.com/rentra-staging';
if (PHP_SAPI !== 'cli' || realpath(__DIR__.'/..') !== realpath($root)) {
    exit(1);
}
umask(0077);
$lock = fopen($root.'/storage/framework/staging-web-push-cron.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (config('app.env') !== 'staging' || parse_url(config('app.url'), PHP_URL_HOST) !== 'staging.tenantism.com'
    || config('push.environment') !== 'staging' || config('push.project_id') !== 'tenantism-staging' || ! function_exists('proc_open')) {
    fwrite(STDERR, "Staging cron isolation/configuration check failed.\n");
    exit(1);
}
$heartbeat = $root.'/storage/app/private/staging-web-push-cron.json';
$previous = is_file($heartbeat) ? json_decode(file_get_contents($heartbeat), true) : [];
$minute = gmdate('YmdHi');
if (($previous['minute'] ?? null) === $minute && ($previous['status'] ?? null) === 'complete') {
    exit(0);
}
$state = ['minute' => $minute, 'started_at' => gmdate(DATE_ATOM), 'status' => 'running', 'source' => getenv('TENANTISM_CRON_SOURCE') ?: 'hpanel'];
file_put_contents($heartbeat, json_encode($state));
try {
    $state['scheduler_exit'] = $kernel->call('schedule:run');
    $state['worker_exit'] = $kernel->call('queue:work', [
        'connection' => 'database', '--queue' => 'web-push', '--stop-when-empty' => true,
        '--tries' => 3, '--timeout' => 60, '--max-time' => 50,
    ]);
    $state['status'] = $state['scheduler_exit'] === 0 && $state['worker_exit'] === 0 ? 'complete' : 'failed';
} catch (Throwable $error) {
    $state['status'] = 'failed';
    $state['exception_class'] = get_class($error);
} finally {
    $state['finished_at'] = gmdate(DATE_ATOM);
    file_put_contents($heartbeat, json_encode($state, JSON_PRETTY_PRINT));
    flock($lock, LOCK_UN);
    fclose($lock);
}
echo json_encode($state).PHP_EOL;
exit($state['status'] === 'complete' ? 0 : 1);
