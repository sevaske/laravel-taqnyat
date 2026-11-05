<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bearer token
    |--------------------------------------------------------------------------
    |
    | Create an application with the SMS service in your Taqnyat account
    | (Developer -> Application) and copy its bearer token.
    | https://dev.taqnyat.sa/en/doc/sms/#setup-bearer
    |
    */

    'token' => env('TAQNYAT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default sender name
    |--------------------------------------------------------------------------
    |
    | Used when a message does not specify a sender. Must be an active sender
    | name registered in your Taqnyat account (see "php artisan taqnyat:senders").
    |
    */

    'sender' => env('TAQNYAT_SENDER'),

    /*
    |--------------------------------------------------------------------------
    | Timezone of scheduled messages
    |--------------------------------------------------------------------------
    |
    | Scheduled dates are converted to this timezone before they are sent.
    |
    */

    'timezone' => env('TAQNYAT_TIMEZONE', 'Asia/Riyadh'),

    /*
    |--------------------------------------------------------------------------
    | API base URI
    |--------------------------------------------------------------------------
    */

    'base_uri' => env('TAQNYAT_BASE_URI', 'https://api.taqnyat.sa'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    |
    | Timeouts in seconds. To customize the HTTP client further (proxy, retries,
    | logging middleware), rebind TaqnyatServiceProvider::HTTP_CLIENT.
    |
    */

    'http' => [
        'timeout' => env('TAQNYAT_TIMEOUT', 10),
        'connect_timeout' => env('TAQNYAT_CONNECT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message templates
    |--------------------------------------------------------------------------
    |
    | Named message bodies with {placeholders}:
    |
    |     Taqnyat::sendTemplate('966500000000', 'otp', ['code' => 1234]);
    |
    */

    'templates' => [
        // 'otp' => 'Your verification code is {code}',
    ],

];
