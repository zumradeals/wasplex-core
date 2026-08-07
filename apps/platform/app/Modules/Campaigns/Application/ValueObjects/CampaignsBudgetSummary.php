<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\ValueObjects;

final class CampaignsBudgetSummary
{
    public function __construct(
        public readonly int $activeCampaigns,
        public readonly int $totalReservedMinor,
        public readonly int $totalCapturedMinor,
        public readonly int $totalReleasedMinor,
    ) {}
}
