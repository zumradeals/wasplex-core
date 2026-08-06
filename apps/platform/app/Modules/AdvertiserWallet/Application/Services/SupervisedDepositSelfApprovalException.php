<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Shared\Http\AppException;

final class SupervisedDepositSelfApprovalException extends AppException
{
    public function __construct(string $depositId)
    {
        parent::__construct(
            'SUPERVISED_DEPOSIT_SELF_APPROVAL',
            "L'administrateur qui a proposé le dépôt supervisé {$depositId} ne peut pas l'approuver lui-même.",
            ['deposit_id' => $depositId],
            403,
        );
    }
}
