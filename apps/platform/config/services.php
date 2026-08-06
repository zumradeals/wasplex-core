<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md: real Merchant API
     * contract. Sandbox only in P003 — GeniusPayAdapter refuses to boot
     * with environment != "sandbox" or a merchant key containing "live".
     */
    'geniuspay' => [
        'environment' => env('GENIUSPAY_ENVIRONMENT', 'sandbox'),
        'base_url' => env('GENIUSPAY_BASE_URL', 'https://geniuspay.ci/api/v1/merchant'),
        'merchant_key' => env('GENIUSPAY_MERCHANT_KEY'),
        'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),
        'checkout_hosts' => array_filter(explode(',', (string) env('GENIUSPAY_CHECKOUT_HOSTS', 'geniuspay.ci,pay.genius.ci'))),
    ],

];
