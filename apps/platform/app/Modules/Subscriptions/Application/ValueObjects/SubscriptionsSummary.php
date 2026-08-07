<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\ValueObjects;

final class SubscriptionsSummary
{
    public function __construct(
        public readonly int $activeSubscriptions,
        public readonly int $totalRevenueMinor,
    ) {}
}
