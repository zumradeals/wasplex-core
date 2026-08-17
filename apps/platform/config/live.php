<?php

declare(strict_types=1);

return [
    'minimum_segment_size' => (int) env('LIVE_MINIMUM_SEGMENT_SIZE', 20),
    'reward_offer_seconds' => (int) env('LIVE_REWARD_OFFER_SECONDS', 45),
    'rewards_enabled' => filter_var(env('LIVE_REWARDS_ENABLED', true), FILTER_VALIDATE_BOOL),
    'reward_antifraud_mode' => in_array(env('LIVE_REWARD_ANTIFRAUD_MODE', 'observe'), ['observe', 'enforce'], true)
        ? env('LIVE_REWARD_ANTIFRAUD_MODE', 'observe')
        : 'observe',
    'reward_risk_repeat_threshold' => (int) env('LIVE_REWARD_RISK_REPEAT_THRESHOLD', 3),
    'reward_heartbeat_interval_ms' => (int) env('LIVE_REWARD_HEARTBEAT_INTERVAL_MS', 3000),
    'reward_heartbeat_min_interval_ms' => (int) env('LIVE_REWARD_HEARTBEAT_MIN_INTERVAL_MS', 1000),
    'reward_heartbeat_max_gap_ms' => (int) env('LIVE_REWARD_HEARTBEAT_MAX_GAP_MS', 8000),
];
