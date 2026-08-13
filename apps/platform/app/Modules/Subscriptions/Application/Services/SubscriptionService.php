<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Subscriptions\Infrastructure\Models\PlanEconomicClassLink;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEvent;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Shared\Http\AppException;
use App\Shared\Payments\PaymentProviderContract;
use App\Shared\Payments\ValueObjects\CreatePaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * docs/03-abonnements-et-classes-economiques-wasplex.md §15: "choix du
 * plan → récapitulatif → paiement → rapprochement → activation →
 * rattachement à la classe → initialisation du cycle → mise à jour de Mon
 * Espace". This service owns everything up to and including activation;
 * the payment confirmation itself only ever arrives through
 * SubscriptionPaymentWebhookHandler (never trusted from a browser
 * redirect), same discipline as P003's GeniusPayWebhookHandler.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly SubscriptionQuotaContract $quota,
    ) {}

    public function subscribe(string $accountId, string $planVersionId): SubscriptionPayment
    {
        $planVersion = $this->requirePublishedPlan($planVersionId);
        $economicClassId = $this->economicClassIdFor($planVersion);

        $subscription = UserSubscription::query()->create([
            'account_id' => $accountId,
            'plan_version_id' => $planVersion->id,
            'economic_class_id' => $economicClassId,
            'status' => UserSubscription::STATUS_PENDING_PAYMENT,
        ]);

        SubscriptionEvent::query()->create([
            'user_subscription_id' => $subscription->id,
            'event_type' => SubscriptionEvent::TYPE_SELECTED,
            'payload' => ['plan_version_id' => $planVersion->id],
        ]);

        return $this->createPayment($accountId, $planVersion, $subscription->id);
    }

    public function renew(string $subscriptionId): SubscriptionPayment
    {
        $subscription = $this->requireSubscription($subscriptionId);
        $planVersion = $this->requirePublishedPlan($subscription->plan_version_id);

        return $this->createPayment($subscription->account_id, $planVersion, $subscription->id);
    }

    /**
     * docs/03 §17: application immédiate après paiement confirmé, gains
     * passés inchangés, quota déjà consommé conservé, nouveau restant =
     * nouveau quota − quota déjà consommé.
     */
    public function upgrade(string $subscriptionId, string $newPlanVersionId): SubscriptionPayment
    {
        $subscription = $this->requireSubscription($subscriptionId);
        $planVersion = $this->requirePublishedPlan($newPlanVersionId);

        return $this->createPayment($subscription->account_id, $planVersion, $subscription->id, isUpgrade: true);
    }

    /**
     * docs/03 §18: application au prochain cycle, aucun retrait rétroactif.
     * No payment here — the target plan is only scheduled;
     * applyScheduledDowngrades() (run periodically, or directly in tests)
     * moves it into effect once the current period ends.
     */
    public function downgrade(string $subscriptionId, string $newPlanVersionId): UserSubscription
    {
        $subscription = $this->requireSubscription($subscriptionId);
        $planVersion = $this->requirePublishedPlan($newPlanVersionId);

        if (! $subscription->isActive()) {
            throw new InvalidSubscriptionStateException($subscriptionId, 'Abonnement non actif.');
        }

        $subscription->update([
            'status' => UserSubscription::STATUS_SCHEDULED_DOWNGRADE,
            'scheduled_plan_version_id' => $planVersion->id,
        ]);

        SubscriptionEvent::query()->create([
            'user_subscription_id' => $subscription->id,
            'event_type' => SubscriptionEvent::TYPE_DOWNGRADE_SCHEDULED,
            'payload' => ['scheduled_plan_version_id' => $planVersion->id],
        ]);

        return $subscription->refresh();
    }

    /**
     * docs/03 §19: WP et historique restent acquis, Wallet reste
     * accessible, seules les capacités dépendantes du plan sont
     * suspendues — enforced by simply not gating Wallet on subscription
     * status anywhere in this codebase, not by any action taken here.
     */
    public function cancel(string $subscriptionId): UserSubscription
    {
        $subscription = $this->requireSubscription($subscriptionId);

        if (! $subscription->isActive()) {
            throw new InvalidSubscriptionStateException($subscriptionId, 'Abonnement non actif.');
        }

        $subscription->update(['cancelled_at' => now()]);

        SubscriptionEvent::query()->create([
            'user_subscription_id' => $subscription->id,
            'event_type' => SubscriptionEvent::TYPE_SUSPENDED,
            'payload' => ['reason' => 'user_cancelled'],
        ]);

        return $subscription->refresh();
    }

    /**
     * Applies every scheduled_downgrade whose current period has ended.
     * Meant to run periodically (see routes/console.php schedule); also
     * called directly by tests since no real clock-driven scheduler runs
     * in this sandbox.
     */
    public function applyScheduledDowngrades(): int
    {
        $due = UserSubscription::query()
            ->where('status', UserSubscription::STATUS_SCHEDULED_DOWNGRADE)
            ->whereNotNull('scheduled_plan_version_id')
            ->where(function ($query): void {
                $query->whereNull('current_period_end')->orWhere('current_period_end', '<=', now());
            })
            ->get();

        foreach ($due as $subscription) {
            DB::transaction(function () use ($subscription): void {
                $newPlanVersion = SubscriptionPlanVersion::query()->findOrFail($subscription->scheduled_plan_version_id);
                $newClassId = $this->economicClassIdFor($newPlanVersion);

                $subscription->update([
                    'plan_version_id' => $newPlanVersion->id,
                    'economic_class_id' => $newClassId,
                    'scheduled_plan_version_id' => null,
                    'status' => UserSubscription::STATUS_ACTIVE,
                ]);

                $this->syncEntitlements($subscription);
            });
        }

        return $due->count();
    }

    /**
     * docs/03 §19: à expiration, bascule vers la classe prévue (Gratuit) —
     * les WP et le Wallet restent acquis/accessibles, seules les
     * capacités liées au plan sont suspendues.
     */
    public function expireOverdue(): int
    {
        $free = EconomicClass::query()->where('code', EconomicClass::CODE_FREE)->firstOrFail();

        $due = UserSubscription::query()
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($due as $subscription) {
            DB::transaction(function () use ($subscription, $free): void {
                $subscription->update([
                    'status' => UserSubscription::STATUS_EXPIRED,
                    'economic_class_id' => $free->id,
                ]);

                SubscriptionEvent::query()->create([
                    'user_subscription_id' => $subscription->id,
                    'event_type' => SubscriptionEvent::TYPE_EXPIRED,
                ]);

                $this->syncEntitlements($subscription);
            });
        }

        return $due->count();
    }

    /**
     * Called by SubscriptionPaymentWebhookHandler once a payment is
     * server-verified as confirmed — never from the payment creation
     * response or a browser redirect.
     */
    public function activateFromConfirmedPayment(SubscriptionPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $subscription = UserSubscription::query()->findOrFail($payment->user_subscription_id);
            $planVersion = SubscriptionPlanVersion::query()->findOrFail($payment->plan_version_id);
            $wasUpgrade = $subscription->status === UserSubscription::STATUS_ACTIVE
                && $subscription->plan_version_id !== $planVersion->id;

            $previousConsumed = null;
            if ($wasUpgrade) {
                $previousConsumed = $this->quota->currentCounter($subscription->account_id)->quota_consumed;
            }

            $newClassId = $this->economicClassIdFor($planVersion);

            $subscription->update([
                'plan_version_id' => $planVersion->id,
                'economic_class_id' => $newClassId,
                'status' => UserSubscription::STATUS_ACTIVE,
                'started_at' => $subscription->started_at ?? now(),
                'current_period_end' => now()->addDays($planVersion->duration_days),
                'cancelled_at' => null,
            ]);

            $payment->update(['status' => SubscriptionPayment::STATUS_ACTIVATED]);

            SubscriptionEvent::query()->create([
                'user_subscription_id' => $subscription->id,
                'event_type' => SubscriptionEvent::TYPE_PAYMENT_RECEIVED,
                'payload' => ['payment_id' => $payment->id],
            ]);

            SubscriptionEvent::query()->create([
                'user_subscription_id' => $subscription->id,
                'event_type' => $wasUpgrade ? SubscriptionEvent::TYPE_UPGRADED : SubscriptionEvent::TYPE_ACTIVATED,
                'payload' => ['plan_version_id' => $planVersion->id],
            ]);

            // Initialize/reallocate the current cycle right away rather
            // than lazily on first quota touch — matches docs/03 §15's
            // explicit "initialisation du cycle" activation step.
            $counter = $this->quota->currentCounter($subscription->account_id);

            if ($wasUpgrade && $previousConsumed !== null) {
                // docs/03 §17: "nouveau restant = nouveau quota − quota déjà
                // consommé" — the current cycle's allocation is reset to the
                // new class's quota, consumed units carry over unchanged.
                $newQuota = EconomicClassVersion::query()
                    ->where('economic_class_id', $newClassId)
                    ->whereNull('effective_to')
                    ->value('quota_monthly') ?? $counter->quota_allocated;

                $counter->update([
                    'quota_allocated' => $newQuota,
                    'quota_consumed' => min($previousConsumed, $newQuota),
                ]);
            }

            $this->syncEntitlements($subscription);
        });
    }

    /**
     * Réconciliation déclenchée depuis le retour navigateur. Le navigateur
     * ne confirme jamais le paiement : Wasplex relit le statut directement
     * chez GeniusPay avant toute activation.
     */
    public function reconcilePayment(string $paymentId, string $accountId): SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()
            ->whereKey($paymentId)
            ->where('account_id', $accountId)
            ->first();

        if ($payment === null) {
            throw new AppException('SUBSCRIPTION_PAYMENT_NOT_FOUND', 'Paiement d’abonnement introuvable.', [], 404);
        }

        if ($payment->isTerminal()) {
            return $payment;
        }

        if ($payment->provider_reference === null) {
            throw new AppException(
                'SUBSCRIPTION_PAYMENT_NOT_STARTED',
                'Le paiement n’a pas encore de référence GeniusPay.',
                ['payment_id' => $payment->id],
                409,
            );
        }

        $verified = $this->provider->fetchPaymentStatus($payment->provider_reference);

        if ($verified->amountMinor !== null && $verified->amountMinor !== $payment->amount_minor) {
            throw new AppException(
                'SUBSCRIPTION_PAYMENT_AMOUNT_MISMATCH',
                'Le montant confirmé par le prestataire ne correspond pas au paiement attendu.',
                ['payment_id' => $payment->id],
                409,
            );
        }

        if ($verified->currency !== null && $verified->currency !== $payment->currency) {
            throw new AppException(
                'SUBSCRIPTION_PAYMENT_CURRENCY_MISMATCH',
                'La devise confirmée par le prestataire ne correspond pas au paiement attendu.',
                ['payment_id' => $payment->id],
                409,
            );
        }

        if ($verified->normalizedStatus === 'confirmed') {
            $payment->update(['status' => SubscriptionPayment::STATUS_CONFIRMED]);
            $this->activateFromConfirmedPayment($payment->refresh());
        } elseif ($verified->normalizedStatus === 'rejected') {
            $payment->update(['status' => SubscriptionPayment::STATUS_REJECTED]);
        } elseif ($verified->normalizedStatus === 'expired') {
            $payment->update(['status' => SubscriptionPayment::STATUS_EXPIRED]);
        } elseif ($verified->normalizedStatus === 'awaiting_payment') {
            $payment->update(['status' => SubscriptionPayment::STATUS_AWAITING_PAYMENT]);
        }

        return $payment->refresh();
    }

    private function createPayment(string $accountId, SubscriptionPlanVersion $planVersion, string $subscriptionId, bool $isUpgrade = false): SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()->create([
            'account_id' => $accountId,
            'plan_version_id' => $planVersion->id,
            'user_subscription_id' => $subscriptionId,
            'amount_minor' => $planVersion->price_minor,
            'currency' => $planVersion->currency,
            'status' => SubscriptionPayment::STATUS_CREATED,
            'provider_code' => 'geniuspay',
            'idempotency_key' => (string) Str::ulid(),
        ]);

        // Free plans (price 0) skip the provider entirely and activate
        // immediately — there is nothing for GeniusPay to charge.
        if ($planVersion->price_minor === 0) {
            $payment->update(['status' => SubscriptionPayment::STATUS_CONFIRMED]);
            $this->activateFromConfirmedPayment($payment->refresh());

            return $payment->refresh();
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $payment->amount_minor,
            currency: $payment->currency,
            internalReference: $payment->id,
            idempotencyKey: $payment->idempotency_key,
            successUrl: $appUrl.'/app?tab=espace&section=subscription&payment=subscription-success&payment_id='.$payment->id,
            errorUrl: $appUrl.'/app?tab=espace&section=subscription&payment=subscription-failed&payment_id='.$payment->id,
            description: "Abonnement Wasplex {$planVersion->id}",
            metadata: [
                'payment_context' => 'subscription',
                'subscription_payment_id' => $payment->id,
                'user_subscription_id' => $subscriptionId,
                'is_upgrade' => $isUpgrade,
            ],
        ));

        $payment->update([
            'status' => $this->mapProviderStatus($result->normalizedStatus),
            'provider_reference' => $result->providerReference,
            'checkout_url' => $result->checkoutUrl,
            'metadata' => ['environment' => $result->environment, 'is_upgrade' => $isUpgrade],
        ]);

        return $payment->refresh();
    }

    private function mapProviderStatus(string $normalizedStatus): string
    {
        return match ($normalizedStatus) {
            'awaiting_payment' => SubscriptionPayment::STATUS_AWAITING_PAYMENT,
            'confirmed' => SubscriptionPayment::STATUS_CONFIRMED,
            'rejected' => SubscriptionPayment::STATUS_REJECTED,
            'expired' => SubscriptionPayment::STATUS_EXPIRED,
            default => SubscriptionPayment::STATUS_CREATED,
        };
    }

    private function economicClassIdFor(SubscriptionPlanVersion $planVersion): string
    {
        $link = PlanEconomicClassLink::query()->where('plan_version_id', $planVersion->id)->first();

        if ($link === null) {
            throw new PlanNotAvailableException($planVersion->id);
        }

        return $link->economic_class_id;
    }

    private function requirePublishedPlan(string $planVersionId): SubscriptionPlanVersion
    {
        $planVersion = SubscriptionPlanVersion::query()->find($planVersionId);

        if ($planVersion === null || ! $planVersion->isPublished()) {
            throw new PlanNotAvailableException($planVersionId);
        }

        return $planVersion;
    }

    private function requireSubscription(string $subscriptionId): UserSubscription
    {
        $subscription = UserSubscription::query()->find($subscriptionId);

        if ($subscription === null) {
            throw new SubscriptionNotFoundException($subscriptionId);
        }

        return $subscription;
    }

    /**
     * docs/03 §14: "le plan Gratuit n'est pas éligible au programme
     * Fonds." Recomputed whenever the active economic class changes —
     * Fonds itself doesn't exist yet (P014), this only stores the flag.
     */
    private function syncEntitlements(UserSubscription $subscription): void
    {
        $eligible = $subscription->economic_class_id !== null
            && EconomicClass::query()->whereKey($subscription->economic_class_id)->where('code', '!=', EconomicClass::CODE_FREE)->exists();

        SubscriptionEntitlement::query()->updateOrCreate(
            ['user_subscription_id' => $subscription->id, 'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE],
            ['enabled' => $eligible],
        );
    }
}
