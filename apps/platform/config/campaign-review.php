<?php

return [
    'require_distinct_decider' => (bool) env('CAMPAIGN_REVIEW_REQUIRE_DISTINCT_DECIDER', false),
    'default_due_hours' => (int) env('CAMPAIGN_REVIEW_DEFAULT_DUE_HOURS', 24),
];
