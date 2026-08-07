<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\ValueObjects;

/**
 * Delivery aggregates for a campaign (or, for `globalStats()`, the whole
 * Feed) — the reporting source of truth for Feed is "AdDelivered +
 * QualifiedAttention" (docs/18-reporting-statistiques-audit-observabilite-wasplex.md
 * §6), read directly from feed_ad_deliveries, no analytics pipeline.
 */
final class FeedCampaignStats
{
    public function __construct(
        public readonly int $reserved,
        public readonly int $started,
        public readonly int $completed,
        public readonly int $abandoned,
        public readonly int $expired,
        public readonly int $held,
        public readonly int $rejected,
        public readonly int $gainDistributedMinor,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0, 0, 0, 0, 0, 0);
    }

    /**
     * Total deliveries ever created for the campaign, regardless of their
     * current status (`status` reflects the *current* state only — a
     * completed delivery is no longer counted under `started`).
     */
    public function totalDeliveries(): int
    {
        return $this->reserved + $this->started + $this->completed
            + $this->abandoned + $this->expired + $this->held + $this->rejected;
    }

    /**
     * completed / total deliveries — 0 when none exist, never a division
     * by zero.
     */
    public function attentionRate(): float
    {
        $total = $this->totalDeliveries();

        return $total > 0 ? $this->completed / $total : 0.0;
    }
}
