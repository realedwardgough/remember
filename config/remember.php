<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    |
    | Enable account invitation emails. Your Laravel mailer must also be
    | configured through the standard MAIL_* environment variables before
    | notifications can be delivered.
    |
    */

    'email_notifications' => env(key: 'EMAIL_NOTIFICATIONS', default: false),

    'realtime' => [
        'enabled' => env('BROADCAST_CONNECTION') === 'reverb',
        'key' => env('REVERB_APP_KEY', ''),
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST', '127.0.0.1')),
        'port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 8080)),
        'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'http')),
    ],

];
