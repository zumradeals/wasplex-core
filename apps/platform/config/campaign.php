<?php

return [
    'sandbox_cpm_minor' => (int) env('CAMPAIGN_SANDBOX_CPM_MINOR', 1000),
    'minimum_budget_minor' => (int) env('CAMPAIGN_MINIMUM_BUDGET_MINOR', 5000),
    'quote_validity_hours' => (int) env('CAMPAIGN_QUOTE_VALIDITY_HOURS', 48),
    'reservation_validity_days' => (int) env('CAMPAIGN_RESERVATION_VALIDITY_DAYS', 30),
    'planning_reach_per_radius_km' => (int) env('CAMPAIGN_PLANNING_REACH_PER_RADIUS_KM', 4000),
];
