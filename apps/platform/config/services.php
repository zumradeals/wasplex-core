<?php

return [
    'geniuspay' => [
        'mode' => env('GENIUSPAY_MODE', 'sandbox'),
        'base_url' => env('GENIUSPAY_BASE_URL', 'https://geniuspay.ci/api/v1/merchant'),
        'checkout_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('GENIUSPAY_CHECKOUT_HOSTS', 'geniuspay.ci,pay.genius.ci')),
        ))),
        'api_key' => env('GENIUSPAY_API_KEY'),
        'api_secret' => env('GENIUSPAY_API_SECRET'),
        'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('GENIUSPAY_WEBHOOK_TOLERANCE_SECONDS', 300),
        'minimum_deposit_minor' => (int) env('GENIUSPAY_MINIMUM_DEPOSIT_MINOR', 200),
        'maximum_deposit_minor' => (int) env('GENIUSPAY_MAXIMUM_DEPOSIT_MINOR', 5000000),
        'allow_production_activation' => (bool) env('GENIUSPAY_ALLOW_PRODUCTION_ACTIVATION', false),
    ],
];
