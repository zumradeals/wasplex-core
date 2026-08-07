<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDepositEvent;
use App\Shared\Reconciliation\ReconcilablePaymentDirectoryContract;
use App\Shared\Reconciliation\ReconcilablePaymentRecord;
use Illuminate\Support\Carbon;

final class AdvertiserWalletReconcilablePaymentDirectory implements ReconcilablePaymentDirectoryContract
{
    public function sourceModule(): string
    {
        return 'advertiser_wallet_deposit';
    }

    /**
     * @return array<int, ReconcilablePaymentRecord>
     */
    public function pending(): array
    {
        $cutoff = Carbon::now('UTC')->subDays((int) config('reconciliation.lookback_days'));

        $deposits = AdvertiserWalletDeposit::query()
            // docs/chantiers/P013-CHANTIER.md : un dépôt supervisé
            // (docs/13 §28) n'est jamais soumis à GeniusPay — le
            // rapprocher reviendrait à interroger le prestataire pour une
            // référence qu'il n'a jamais émise (défaut trouvé en
            // rejouant la verticale de bout en bout).
            ->where('provider_code', '!=', 'manual_supervised')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNotIn('status', AdvertiserWalletDeposit::TERMINAL_STATUSES)
                    ->orWhere('created_at', '>=', $cutoff);
            })
            ->get();

        $eventCounts = AdvertiserWalletDepositEvent::query()
            ->whereIn('deposit_id', $deposits->pluck('id'))
            ->where('signature_valid', true)
            ->selectRaw('deposit_id, count(*) as event_count')
            ->groupBy('deposit_id')
            ->pluck('event_count', 'deposit_id');

        return $deposits->map(fn (AdvertiserWalletDeposit $deposit): ReconcilablePaymentRecord => new ReconcilablePaymentRecord(
            sourceModule: $this->sourceModule(),
            sourceRecordId: $deposit->id,
            providerReference: $deposit->provider_reference,
            amountMinor: $deposit->amount_minor,
            currency: $deposit->currency,
            internalStatus: $deposit->status,
            ledgerTransactionId: $deposit->ledger_transaction_id,
            webhookEventCount: (int) ($eventCounts[$deposit->id] ?? 0),
            createdAt: $deposit->created_at,
        ))->all();
    }
}
