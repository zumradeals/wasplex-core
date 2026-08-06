<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Shared\Http\AppException;

final class SubscriptionNotFoundException extends AppException
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct('SUBSCRIPTION_NOT_FOUND', "Abonnement « {$subscriptionId} » introuvable.", ['subscription_id' => $subscriptionId], 404);
    }
}
