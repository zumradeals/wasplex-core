<?php

use App\Modules\Identity\Infrastructure\Models\Account;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => 'accounts',
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
    'passwords' => [
        'accounts' => [
            'provider' => 'accounts',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
