<?php

return [
    'minimum_segment_size' => (int) env('ADVERTISING_MINIMUM_SEGMENT_SIZE', 25),
    'estimate_rounding_step' => (int) env('ADVERTISING_ESTIMATE_ROUNDING_STEP', 10),
    'frequency_window_hours' => (int) env('ADVERTISING_FREQUENCY_WINDOW_HOURS', 24),
    'frequency_limit' => (int) env('ADVERTISING_FREQUENCY_LIMIT', 3),
    'fatigue_limit' => (int) env('ADVERTISING_FATIGUE_LIMIT', 100),
    'subject_hash_key' => env('ADVERTISING_SUBJECT_HASH_KEY', env('APP_KEY')),
];
