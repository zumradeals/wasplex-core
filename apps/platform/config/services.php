<?php

return [
    'geniuspay' => [
        'mode' => env('GENIUSPAY_MODE', 'sandbox'),
        'base_url' => env('GENIUSPAY_BASE_URL', 'https://pay.genius.ci/api/v1/merchant'),
        'api_key' => env('GENIUSPAY_API_KEY'),
        'api_secret' => env('GENIUSPAY_API_SECRET'),
        'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('GENIUSPAY_WEBHOOK_TOLERANCE_SECONDS', 300),
        'minimum_deposit_minor' => (int) env('GENIUSPAY_MINIMUM_DEPOSIT_MINOR', 200),
        'maximum_deposit_minor' => (int) env('GENIUSPAY_MAXIMUM_DEPOSIT_MINOR', 5000000),
    ],
];
