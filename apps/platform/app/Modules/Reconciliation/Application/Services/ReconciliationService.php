<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application\Services;

use App\Modules\Reconciliation\Infrastructure\Models\ReconciliationEntry;
use App\Modules\Reconciliation\Infrastructure\Models\ReconciliationRun;
use App\Shared\Payments\PaymentProviderContract;
use App\Shared\Payments\ValueObjects\ProviderPaymentResult;
use App\Shared\Reconciliation\ReconcilablePaymentDirectoryContract;
use App\Shared\Reconciliation\ReconcilablePaymentRecord;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * docs/06-wallet-et-grand-livre-wasplex.md §34 : compare l'état interne de
 * chaque paiement GeniusPay (dépôt annonceur, paiement d'abonnement) avec
 * une revérification serveur en direct (docs/chantiers/
 * P011-B-RAPPROCHEMENT.md §1 : GeniusPay sandbox n'expose aucun relevé,
 * seulement un lookup par référence) et le journal webhook déjà stocké.
 * Ne modifie jamais un solde ni une écriture — alimente uniquement une file
 * de revue (CLAUDE.md §7).
 */
final class ReconciliationService
{
    /**
     * @param  iterable<int, ReconcilablePaymentDirectoryContract>  $directories
     */
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly iterable $directories,
    ) {}

    public function run(?string $triggeredBy = null): ReconciliationRun
    {
        $run = ReconciliationRun::query()->create([
            'triggered_by' => $triggeredBy,
            'started_at' => Carbon::now('UTC'),
        ]);

        $summary = [];
        $total = 0;

        foreach ($this->directories as $directory) {
            foreach ($directory->pending() as $record) {
                $total++;
                [$resultCode, $providerStatusRaw, $notes] = $this->classify($record);

                ReconciliationEntry::query()->create([
                    'run_id' => $run->id,
                    'source_module' => $record->sourceModule,
                    'source_record_id' => $record->sourceRecordId,
                    'provider_reference' => $record->providerReference,
                    'amount_minor' => $record->amountMinor,
                    'currency' => $record->currency,
                    'internal_status' => $record->internalStatus,
                    'provider_status_raw' => $providerStatusRaw,
                    'result_code' => $resultCode,
                    'notes' => $notes,
                ]);

                $summary[$resultCode] = ($summary[$resultCode] ?? 0) + 1;
            }
        }

        $run->update([
            'completed_at' => Carbon::now('UTC'),
            'total_checked' => $total,
            'summary' => $summary,
        ]);

        return $run->refresh();
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function classify(ReconcilablePaymentRecord $record): array
    {
        if ($record->providerReference === null) {
            return [ReconciliationEntry::RESULT_MANUAL_REVIEW, null, 'Aucune référence prestataire enregistrée — paiement jamais initié côté prestataire.'];
        }

        if ($record->webhookEventCount > 1) {
            return [ReconciliationEntry::RESULT_DUPLICATE, null, "{$record->webhookEventCount} webhooks valides reçus pour cette référence."];
        }

        try {
            $status = $this->provider->fetchPaymentStatus($record->providerReference);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return [ReconciliationEntry::RESULT_UNMATCHED, null, 'Référence introuvable chez le prestataire.'];
            }

            return [ReconciliationEntry::RESULT_MANUAL_REVIEW, null, "Revérification prestataire indisponible (HTTP {$exception->response->status()})."];
        } catch (Throwable $exception) {
            return [ReconciliationEntry::RESULT_MANUAL_REVIEW, null, 'Revérification prestataire indisponible : '.$exception->getMessage()];
        }

        return $this->compare($record, $status);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function compare(ReconcilablePaymentRecord $record, ProviderPaymentResult $status): array
    {
        $internalBucket = $this->bucketInternalStatus($record->internalStatus);
        $providerBucket = $status->normalizedStatus;

        if ($internalBucket === 'confirmed' && in_array($providerBucket, ['rejected', 'expired'], true)) {
            return [ReconciliationEntry::RESULT_MANUAL_REVIEW, $status->providerStatusRaw, "Inversion prestataire : déjà crédité en interne mais le prestataire indique désormais « {$providerBucket} »."];
        }

        if ($internalBucket !== $providerBucket) {
            return [ReconciliationEntry::RESULT_STATUS_MISMATCH, $status->providerStatusRaw, "Statut interne « {$record->internalStatus} » incompatible avec le statut prestataire « {$providerBucket} »."];
        }

        $tolerance = (int) config('reconciliation.amount_tolerance_minor');

        if ($status->amountMinor !== null && abs($status->amountMinor - $record->amountMinor) > $tolerance) {
            return [ReconciliationEntry::RESULT_AMOUNT_MISMATCH, $status->providerStatusRaw, "Montant interne {$record->amountMinor} différent du montant prestataire {$status->amountMinor}."];
        }

        if ($status->currency !== null && $status->currency !== $record->currency) {
            return [ReconciliationEntry::RESULT_AMOUNT_MISMATCH, $status->providerStatusRaw, "Devise interne {$record->currency} différente de la devise prestataire {$status->currency}."];
        }

        if ($internalBucket === 'awaiting_payment') {
            return [ReconciliationEntry::RESULT_PARTIALLY_MATCHED, $status->providerStatusRaw, 'Toujours en attente des deux côtés — non résolu.'];
        }

        return [ReconciliationEntry::RESULT_MATCHED, $status->providerStatusRaw, null];
    }

    private function bucketInternalStatus(string $status): string
    {
        return match ($status) {
            'created', 'awaiting_payment' => 'awaiting_payment',
            'confirmed', 'credited', 'activated' => 'confirmed',
            'rejected' => 'rejected',
            'expired' => 'expired',
            default => 'unknown',
        };
    }
}
