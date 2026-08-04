<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marché initial
    |--------------------------------------------------------------------------
    |
    | Wasplex reste international. Le Mali est uniquement le premier marché
    | proposé par défaut et ne doit jamais être utilisé comme une frontière
    | technique empêchant l'activation d'autres pays.
    |
    */
    'default_market' => env('PROFILE_INTELLIGENCE_DEFAULT_MARKET', 'ML'),
    'default_locale' => env('PROFILE_INTELLIGENCE_DEFAULT_LOCALE', 'fr'),

    /*
    |--------------------------------------------------------------------------
    | Gouvernance des signaux assistés par IA
    |--------------------------------------------------------------------------
    |
    | L'IA peut proposer, classer et expliquer. Une proposition dérivée ne
    | devient jamais un fait de ciblage tant que l'utilisateur ne l'a pas
    | confirmée ou qu'une politique explicite ne l'autorise pas.
    |
    */
    'ai' => [
        'enabled' => env('PROFILE_INTELLIGENCE_AI_ENABLED', false),
        'minimum_confidence' => (float) env('PROFILE_INTELLIGENCE_AI_MINIMUM_CONFIDENCE', 0.75),
        'require_user_confirmation' => true,
    ],

    'forbidden_signal_kinds' => [
        'health',
        'religion',
        'politics',
        'sexual_orientation',
        'pregnancy_inference',
        'financial_vulnerability',
        'personal_debt',
        'criminal_history',
        'kyc',
        'alerts',
        'mutual_aid_funds',
    ],
];
