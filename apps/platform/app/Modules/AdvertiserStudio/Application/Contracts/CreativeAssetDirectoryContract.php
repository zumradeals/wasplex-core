<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Contracts;

use App\Modules\AdvertiserStudio\Application\ValueObjects\CreativeAssetSummary;

/**
 * The only way another module (Campaigns, P006) may verify a creative
 * asset belongs to the calling organization and read the minimal
 * projection needed to render a preview — no direct query against
 * `creative_assets` from outside AdvertiserStudio (docs/CLAUDE.md §6).
 */
interface CreativeAssetDirectoryContract
{
    public function find(string $organizationId, string $assetId): ?CreativeAssetSummary;
}
