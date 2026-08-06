<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Shared\Http\AppException;

final class NoActiveSubscriptionException extends AppException
{
    public function __construct(string $accountId)
    {
        parent::__construct('NO_ACTIVE_SUBSCRIPTION', "Aucun abonnement actif pour le compte {$accountId}.", status: 404);
    }
}
