<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Shared\Reconciliation\ReconcilablePaymentDirectoryContract;
use App\Shared\Reconciliation\ReconcilablePaymentRecord;
use Illuminate\Support\Carbon;

/**
 * Unlike AdvertiserWallet's deposits, subscription payments have no
 * persisted webhook event log (docs/chantiers/P011-B-RAPPROCHEMENT.md §8) —
 * `webhookEventCount` is therefore a best-effort 1 once terminal, 0
 * otherwise, not a true delivery count. Duplicate detection for this source
 * module relies on re-verification mismatches, not delivery multiplicity.
 */
final class SubscriptionsReconcilablePaymentDirectory implements ReconcilablePaymentDirectoryContract
{
    public function sourceModule(): string
    {
        return 'subscription_payment';
    }

    /**
     * @return array<int, ReconcilablePaymentRecord>
     */
    public function pending(): array
    {
        $cutoff = Carbon::now('UTC')->subDays((int) config('reconciliation.lookback_days'));

        $payments = SubscriptionPayment::query()
            ->where(function ($query) use ($cutoff): void {
                $query->whereNotIn('status', SubscriptionPayment::TERMINAL_STATUSES)
                    ->orWhere('created_at', '>=', $cutoff);
            })
            ->get();

        return $payments->map(fn (SubscriptionPayment $payment): ReconcilablePaymentRecord => new ReconcilablePaymentRecord(
            sourceModule: $this->sourceModule(),
            sourceRecordId: $payment->id,
            providerReference: $payment->provider_reference,
            amountMinor: $payment->amount_minor,
            currency: $payment->currency,
            internalStatus: $payment->status,
            ledgerTransactionId: null,
            webhookEventCount: $payment->isTerminal() ? 1 : 0,
            createdAt: $payment->created_at,
        ))->all();
    }
}
