<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Controllers\User;

use App\Modules\Subscriptions\Application\Services\FreeSubscriptionProvisioner;
use App\Modules\Subscriptions\Application\Services\NoActiveSubscriptionException;
use App\Modules\Subscriptions\Application\Services\SubscriptionNotFoundException;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Application\Services\SubscriptionService;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEvent;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SubscriptionsController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SubscriptionQuotaContract $quota,
        private readonly FreeSubscriptionProvisioner $freeSubscription,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $this->freeSubscription->ensureFor($accountId);

        $subscription = UserSubscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE, UserSubscription::STATUS_PENDING_PAYMENT])
            ->latest('created_at')
            ->first();

        return response()->json(['subscription' => $subscription]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_version_id' => ['required', 'string']]);

        $payment = $this->subscriptions->subscribe((string) $request->user()->id, $data['plan_version_id']);

        return response()->json(['payment' => $payment], 201);
    }

    public function refreshPayment(Request $request, string $payment): JsonResponse
    {
        $updated = $this->subscriptions->reconcilePayment($payment, (string) $request->user()->id);

        return response()->json(['payment' => $updated]);
    }

    public function renew(Request $request, string $subscription): JsonResponse
    {
        $this->ownedSubscription($request, $subscription);

        $payment = $this->subscriptions->renew($subscription);

        return response()->json(['payment' => $payment]);
    }

    /**
     * docs/03 §24 lists a single "upgrade" endpoint — no dedicated
     * downgrade route exists in the corpus, even though §18 describes
     * downgrade behavior in detail. Interpreted here as "change plan": the
     * server decides upgrade (immediate, paid) vs downgrade (scheduled,
     * free) by comparing the target class's monthly quota to the current
     * one, per §17/§18. Documented as a chantier decision in
     * docs/chantiers/P004-RAPPORT.md.
     */
    public function upgrade(Request $request, string $subscription): JsonResponse
    {
        $data = $request->validate(['plan_version_id' => ['required', 'string']]);
        $current = $this->ownedSubscription($request, $subscription);

        $currentQuota = EconomicClassVersion::query()
            ->where('economic_class_id', $current->economic_class_id)
            ->whereNull('effective_to')
            ->value('quota_monthly') ?? 0;

        $targetPlanVersion = SubscriptionPlanVersion::query()->findOrFail($data['plan_version_id']);
        $targetClassId = $targetPlanVersion->economicClassLink?->economic_class_id;
        $targetQuota = EconomicClassVersion::query()
            ->where('economic_class_id', $targetClassId)
            ->whereNull('effective_to')
            ->value('quota_monthly') ?? 0;

        if ($targetQuota >= $currentQuota) {
            $payment = $this->subscriptions->upgrade($subscription, $data['plan_version_id']);

            return response()->json(['type' => 'upgrade', 'payment' => $payment]);
        }

        $updated = $this->subscriptions->downgrade($subscription, $data['plan_version_id']);

        return response()->json(['type' => 'downgrade_scheduled', 'subscription' => $updated]);
    }

    public function cancel(Request $request, string $subscription): JsonResponse
    {
        $this->ownedSubscription($request, $subscription);

        $updated = $this->subscriptions->cancel($subscription);

        return response()->json(['subscription' => $updated]);
    }

    public function quota(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $this->freeSubscription->ensureFor($accountId);

        try {
            $counter = $this->quota->currentCounter($accountId);
        } catch (NoActiveSubscriptionException) {
            return response()->json(['quota' => null]);
        }

        return response()->json(['quota' => [
            'allocated' => $counter->quota_allocated,
            'consumed' => $counter->quota_consumed,
            'remaining' => $counter->remaining(),
        ]]);
    }

    public function history(Request $request): JsonResponse
    {
        $subscriptionIds = UserSubscription::query()
            ->where('account_id', (string) $request->user()->id)
            ->pluck('id');

        $events = SubscriptionEvent::query()
            ->whereIn('user_subscription_id', $subscriptionIds)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['events' => $events]);
    }

    private function ownedSubscription(Request $request, string $subscriptionId): UserSubscription
    {
        $subscription = UserSubscription::query()->find($subscriptionId);

        if ($subscription === null || $subscription->account_id !== (string) $request->user()->id) {
            throw new SubscriptionNotFoundException($subscriptionId);
        }

        return $subscription;
    }
}
