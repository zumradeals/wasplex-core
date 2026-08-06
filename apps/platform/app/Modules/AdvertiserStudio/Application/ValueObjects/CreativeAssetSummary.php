<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\ValueObjects;

final class CreativeAssetSummary
{
    public function __construct(
        public readonly string $id,
        public readonly string $brandId,
        public readonly string $type,
        public readonly string $url,
        public readonly string $moderationStatus,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?int $duration,
    ) {}
}
