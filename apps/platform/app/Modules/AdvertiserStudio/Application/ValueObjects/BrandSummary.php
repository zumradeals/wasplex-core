<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\ValueObjects;

final class BrandSummary
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly ?string $primaryLogoAssetId,
    ) {}
}
