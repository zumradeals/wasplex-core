<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Shared\Http\AppException;

final class DepositNotFoundException extends AppException
{
    public function __construct(string $depositId)
    {
        parent::__construct('DEPOSIT_NOT_FOUND', "Dépôt « {$depositId} » introuvable.", ['deposit_id' => $depositId], 404);
    }
}
