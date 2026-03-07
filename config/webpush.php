<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Configuration for Web Push (minishlink/web-push)
    |--------------------------------------------------------------------------
    |
    | Generate keys with:  php artisan webpush:vapid
    | Or manually with openssl (see README).
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@enviosdominicana.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],
];
