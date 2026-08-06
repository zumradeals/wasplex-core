<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Contracts;

use App\Modules\AdvertiserStudio\Application\ValueObjects\BrandSummary;

/**
 * The only way another module (Campaigns, P006) may verify a brand belongs
 * to the calling organization and read its minimal summary — no direct
 * query against the `brands` table from outside AdvertiserStudio
 * (docs/CLAUDE.md §6).
 */
interface BrandDirectoryContract
{
    public function find(string $organizationId, string $brandId): ?BrandSummary;
}
