<?php

return [
    'feed_session_ttl_seconds' => (int) env('DISTRIBUTION_FEED_SESSION_TTL_SECONDS', 900),
    'delivery_lease_ttl_seconds' => (int) env('DISTRIBUTION_DELIVERY_LEASE_TTL_SECONDS', 30),
    'match_ttl_minutes' => (int) env('DISTRIBUTION_MATCH_TTL_MINUTES', 60),
    'candidate_limit' => (int) env('DISTRIBUTION_CANDIDATE_LIMIT', 50),
    'media_load_timeout_ms' => (int) env('DISTRIBUTION_MEDIA_LOAD_TIMEOUT_MS', 15000),
    'default_market' => env('DISTRIBUTION_DEFAULT_MARKET', env('PROFILE_INTELLIGENCE_DEFAULT_MARKET', 'CI')),
    'default_currency' => env('DISTRIBUTION_DEFAULT_CURRENCY', 'XOF'),
];
