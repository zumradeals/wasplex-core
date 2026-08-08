<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Application\Contracts\SubscriptionsReportingContract;
use App\Modules\Subscriptions\Application\ValueObjects\SubscriptionsSummary;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;

final class SubscriptionsReportingService implements SubscriptionsReportingContract
{
    public function summary(): SubscriptionsSummary
    {
        $active = UserSubscription::query()
            ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
            ->count();

        $revenue = (int) SubscriptionPayment::query()
            ->whereIn('status', [SubscriptionPayment::STATUS_CONFIRMED, SubscriptionPayment::STATUS_ACTIVATED])
            ->sum('amount_minor');

        return new SubscriptionsSummary($active, $revenue);
    }

    public function activeEconomicClassCodeForAccount(string $accountId): ?string
    {
        $subscription = UserSubscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
            ->with('economicClass')
            ->latest('id')
            ->first();

        return $subscription?->economicClass?->code;
    }
}
