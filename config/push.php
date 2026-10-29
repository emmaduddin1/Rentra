<?php

return [
    'enabled' => (bool) env('PUSH_NOTIFICATIONS_ENABLED', false),
    'environment' => env('PUSH_ENVIRONMENT', env('APP_ENV', 'local')),
    // Deliberately independent of the application's default queue; never send inline.
    'connection' => env('PUSH_QUEUE_CONNECTION', 'database'),
    'queue' => 'web-push',
    'stale_days' => 90,
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'credentials' => env('FIREBASE_CREDENTIALS'),
    'vapid_key' => env('FIREBASE_WEB_VAPID_PUBLIC_KEY'),
    'web' => [
        'apiKey' => env('FIREBASE_WEB_API_KEY'),
        'authDomain' => env('FIREBASE_WEB_AUTH_DOMAIN'),
        'projectId' => env('FIREBASE_WEB_PROJECT_ID'),
        'storageBucket' => env('FIREBASE_WEB_STORAGE_BUCKET'),
        'messagingSenderId' => env('FIREBASE_WEB_MESSAGING_SENDER_ID'),
        'appId' => env('FIREBASE_WEB_APP_ID'),
    ],
];
