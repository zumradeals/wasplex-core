<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Shared\Http\AppException;

final class InvalidSubscriptionStateException extends AppException
{
    public function __construct(string $subscriptionId, string $reason)
    {
        parent::__construct('SUBSCRIPTION_INVALID_STATE', $reason, ['subscription_id' => $subscriptionId], 409);
    }
}
