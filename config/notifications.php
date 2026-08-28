<?php

return [
    'vapid' => [
        'subject' => env('WEBPUSH_VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    ],
    'subscription_failures_before_revoke' => (int) env('WEBPUSH_FAILURES_BEFORE_REVOKE', 3),
    'fcm' => [
        'enabled' => (bool) env('FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        'android_package' => env('FCM_ANDROID_PACKAGE', 'br.rzin.sgc'),
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'timeout' => (int) env('FCM_TIMEOUT', 15),
        'failures_before_revoke' => (int) env('FCM_FAILURES_BEFORE_REVOKE', 3),
    ],
];
