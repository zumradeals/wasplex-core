<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Contracts;

use App\Modules\Campaigns\Application\ValueObjects\CampaignEnvelopeSlot;

/**
 * The only way Feed (P009) may consume a slot of a campaign's already-quoted
 * envelope — never a direct read/write of `campaign_quotes` from outside
 * this module (docs/CLAUDE.md §6). A slot is anonymous: it tracks capacity
 * per (quote, economic class), never who consumed it (docs/chantiers/
 * P009-CHANTIER.md §3).
 */
interface CampaignEnvelopeContract
{
    /**
     * @throws CampaignNotApprovedException
     * @throws CampaignEnvelopeExhaustedException
     */
    public function reserveSlot(string $campaignId, string $economicClass, string $idempotencyKey): CampaignEnvelopeSlot;

    public function captureSlot(string $consumptionId): void;

    public function releaseSlot(string $consumptionId): void;
}
