<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\CampaignModerationContract;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeModerationCase;

final class CampaignModerationService implements CampaignModerationContract
{
    public function __construct(
        private readonly AdvertiserProfileService $profiles,
        private readonly BrandService $brands,
        private readonly CreativeLibraryService $creatives,
    ) {}

    public function approveBundle(
        string $organizationId,
        string $brandId,
        ?string $assetId,
        string $actorAccountId,
    ): void {
        $profile = $this->profiles->getOrCreate($organizationId);
        $this->profiles->verify($profile);

        $brand = $this->brands->find($organizationId, $brandId);
        $this->brands->verify($brand);

        if ($assetId !== null) {
            $asset = $this->creatives->find($organizationId, $assetId);
            $this->creatives->moderate(
                $asset,
                CreativeModerationCase::DECISION_APPROVE,
                $actorAccountId,
            );
        }
    }
}
