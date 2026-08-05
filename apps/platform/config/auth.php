<?php

use App\Modules\Identity\Infrastructure\Models\Account;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'accounts',
        ],
    ],

    'providers' => [
        'accounts' => [
            'driver' => 'eloquent',
            'model' => Account::class,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
