<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Shared\Http\AppException;

final class BrandNotFoundException extends AppException
{
    public function __construct(string $brandId)
    {
        parent::__construct('BRAND_NOT_FOUND', "Marque « {$brandId} » introuvable.", ['brand_id' => $brandId], 404);
    }
}
