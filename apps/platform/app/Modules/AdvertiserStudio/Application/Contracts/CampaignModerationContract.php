<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Contracts;

interface CampaignModerationContract
{
    public function approveBundle(
        string $organizationId,
        string $brandId,
        ?string $assetId,
        string $actorAccountId,
    ): void;
}
