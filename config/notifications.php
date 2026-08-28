<?php

return [
    // Web Push de navegador foi desativado em favor do FCM Android.
    'webpush_enabled' => false,
    'retention' => [
        // A central mantém as lidas por um ano e as não lidas por dois anos.
        'read_days' => (int) env('NOTIFICATION_READ_RETENTION_DAYS', 365),
        'unread_days' => (int) env('NOTIFICATION_UNREAD_RETENTION_DAYS', 730),
    ],
    'fcm' => [
        'enabled' => (bool) env('FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        'android_package' => env('FCM_ANDROID_PACKAGE', 'br.rzin.sgc'),
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'timeout' => (int) env('FCM_TIMEOUT', 15),
        'failures_before_revoke' => (int) env('FCM_FAILURES_BEFORE_REVOKE', 3),
    ],
];
