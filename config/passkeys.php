<?php

$local = in_array(env('APP_ENV', 'production'), ['local', 'testing'], true);
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('WEBAUTHN_ALLOWED_ORIGINS', $local ? env('APP_URL', 'http://localhost') : ''))
)));

return [
    'relying_party_id' => env('WEBAUTHN_RP_ID', $local ? 'localhost' : null),
    'relying_party_name' => env('WEBAUTHN_RP_NAME', 'ZeCoop SGC'),
    'allowed_origins' => $origins,
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', env('APP_KEY')),
    'timeout' => (int) env('WEBAUTHN_CHALLENGE_TTL', 300) * 1000,
    'challenge_ttl' => (int) env('WEBAUTHN_CHALLENGE_TTL', 300),
    'enrollment_ttl' => (int) env('WEBAUTHN_ENROLLMENT_TTL', 600),
    'guard' => 'web',
    'middleware' => ['web'],
    'management_middleware' => [],
    'throttle' => null,
    'redirect' => '/',
];
