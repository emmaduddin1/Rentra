<?php

namespace App\Services\Push;

class PushConfiguration
{
    public function available(): bool
    {
        $environment = config('push.environment');
        $path = config('push.credentials');
        $credentials = $path && is_readable($path) ? json_decode(file_get_contents($path), true) : null;

        return config('push.enabled')
            && in_array($environment, ['local', 'testing', 'staging', 'production'], true)
            && (! in_array(app()->environment(), ['staging', 'production'], true) || app()->environment() === $environment)
            && config('push.project_id') && config('push.project_id') === config('push.web.projectId')
            && is_array($credentials) && ($credentials['project_id'] ?? null) === config('push.project_id')
            && ! empty($credentials['client_email']) && ! empty($credentials['private_key'])
            && config('push.web.apiKey') && config('push.web.appId') && config('push.web.messagingSenderId')
            && config('push.vapid_key')
            && in_array(config('queue.connections.'.config('push.connection').'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true);
    }

    public function publicConfig(): array
    {
        $worker = public_path('firebase-messaging-sw.js');
        $version = is_file($worker) ? substr(hash_file('sha256', $worker), 0, 12) : 'missing';

        return ['enabled' => $this->available(), 'environment' => config('push.environment'),
            'firebase' => config('push.web'), 'vapidKey' => config('push.vapid_key'),
            'workerUrl' => '/firebase-messaging-sw.js?v='.$version];
    }
}
