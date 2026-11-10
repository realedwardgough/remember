<?php

declare(strict_types=1);

return [
    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
        'hostname' => env('TURNSTILE_HOSTNAME', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    ],
    'invitation_lifetime_hours' => 168,
    // Required on a fresh production installation. Set a random token before exposing /setup.
    'setup_token' => env('SETUP_TOKEN', ''),
    'hsts' => env('SECURITY_HSTS', true),
];
