<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Shared\Http\AppException;

final class CreativeAssetNotFoundException extends AppException
{
    public function __construct(string $assetId)
    {
        parent::__construct('CREATIVE_ASSET_NOT_FOUND', "Média « {$assetId} » introuvable.", ['asset_id' => $assetId], 404);
    }
}
