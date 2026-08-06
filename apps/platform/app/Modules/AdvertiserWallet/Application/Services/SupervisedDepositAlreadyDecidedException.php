<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Shared\Http\AppException;

final class SupervisedDepositAlreadyDecidedException extends AppException
{
    public function __construct(string $depositId)
    {
        parent::__construct('SUPERVISED_DEPOSIT_ALREADY_DECIDED', "Dépôt supervisé « {$depositId} » déjà décidé.", ['deposit_id' => $depositId], 409);
    }
}
