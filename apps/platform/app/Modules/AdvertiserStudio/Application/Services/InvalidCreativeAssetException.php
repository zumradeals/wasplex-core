<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Shared\Http\AppException;

final class InvalidCreativeAssetException extends AppException
{
    public function __construct(string $reason)
    {
        parent::__construct('CREATIVE_ASSET_INVALID', $reason, status: 422);
    }
}
