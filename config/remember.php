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

];
