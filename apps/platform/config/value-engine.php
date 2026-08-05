<?php

return [
    'advertising_event_code' => env('VALUE_ENGINE_ADVERTISING_EVENT_CODE', 'AD_QUALIFIED_ATTENTION'),
    'advertising_rule_code' => env('VALUE_ENGINE_ADVERTISING_RULE_CODE', 'advertising.qualified-attention'),
    'user_share_basis_points' => (int) env('VALUE_ENGINE_USER_SHARE_BPS', 5000),
    'wasplex_share_basis_points' => (int) env('VALUE_ENGINE_WASPLEX_SHARE_BPS', 5000),
    'minimum_event_amount_minor' => (int) env('VALUE_ENGINE_MINIMUM_EVENT_AMOUNT_MINOR', 2),
    'required_attention_ms' => (int) env('VALUE_ENGINE_REQUIRED_ATTENTION_MS', 10000),
    'heartbeat_interval_ms' => (int) env('VALUE_ENGINE_HEARTBEAT_INTERVAL_MS', 5000),
    'attempt_ttl_seconds' => (int) env('VALUE_ENGINE_ATTEMPT_TTL_SECONDS', 300),
    'heartbeat_clock_skew_seconds' => (int) env('VALUE_ENGINE_HEARTBEAT_CLOCK_SKEW_SECONDS', 30),
];
