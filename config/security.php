<?php

return [
    'invitation_ttl_hours' => (int) env('ACCESS_INVITATION_TTL_HOURS', 24),
    'invitation_max_attempts' => (int) env('ACCESS_INVITATION_MAX_ATTEMPTS', 5),
    'invitation_code_pepper' => env('ACCESS_INVITATION_CODE_PEPPER', env('APP_KEY')),
    'audit_pepper' => env('SECURITY_AUDIT_PEPPER', env('APP_KEY')),
    'recent_auth_seconds' => (int) env('RECENT_AUTH_TTL', 600),
    'google_jwt_clock_skew_seconds' => (int) env('GOOGLE_JWT_CLOCK_SKEW', 10),
    'rates' => [
        'invitation_create_per_hour' => (int) env('RATE_INVITATION_CREATE', 20),
        'invitation_send_per_hour' => (int) env('RATE_INVITATION_SEND', 20),
        'invitation_token_per_hour' => (int) env('RATE_INVITATION_TOKEN', 10),
        'invitation_code_per_hour' => (int) env('RATE_INVITATION_CODE', 10),
        'webauthn_per_minute' => (int) env('RATE_WEBAUTHN', 10),
        'google_callback_per_minute' => (int) env('RATE_GOOGLE_CALLBACK', 10),
    ],
];
