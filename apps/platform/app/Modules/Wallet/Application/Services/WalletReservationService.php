<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use App\Modules\Wallet\Application\Data\ReservationRequest;
use App\Modules\Wallet\Domain\Enums\ReservationStatus;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Domain\Events\ValueReservationCaptured;
use App\Modules\Wallet\Domain\Events\ValueReservationReleased;
use App\Modules\Wallet\Domain\Events\ValueReserved;
use App\Modules\Wallet\Domain\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletBalanceProjection;
use App\Modules\Wallet\Infrastructure\Models\WalletOperation;
use App\Modules\Wallet\Infrastructure\Models\WalletReservation;
use Illuminate\Support\Facades\DB;

final readonly class WalletReservationService implements WalletReservationContract
{
    public function __construct(private LedgerContract $ledger, private WalletProjector $projector) {}

    public function reserve(ReservationRequest $request): WalletReservation
    {
        return DB::transaction(function () use ($request): WalletReservation {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($request->walletId);
            $existing = WalletReservation::query()
                ->where('wallet_id', $wallet->id)
                ->where('idempotency_key', $request->idempotencyKey)
                ->first();
            $existing ??= WalletReservation::query()
                ->where('wallet_id', $wallet->id)
                ->where('business_reference', $request->businessReference)
                ->first();

            if ($existing instanceof WalletReservation) {
                if ($existing->amount_minor !== $request->amountMinor
                    || $existing->purpose !== $request->purpose
                    || $existing->business_reference !== $request->businessReference) {
                    throw new WalletException('Cette clé d’idempotence appartient déjà à une autre réservation.');
                }

                return $existing;
            }

            if ($wallet->status !== WalletStatus::Active || $wallet->available_ledger_account_id === null || $wallet->reserved_ledger_account_id === null) {
                throw new WalletException('Ce Wallet ne peut pas réserver de valeur.');
            }

            $projection = WalletBalanceProjection::query()->where('wallet_id', $wallet->id)->lockForUpdate()->firstOrFail();
            if ($projection->available_minor < $request->amountMinor) {
                throw new InsufficientWalletBalance;
            }

            $reservation = WalletReservation::query()->create([
                'wallet_id' => $wallet->id,
                'purpose' => $request->purpose,
                'amount_minor' => $request->amountMinor,
                'unit' => $wallet->unit,
                'currency' => $wallet->currency,
                'status' => ReservationStatus::Active,
                'business_reference' => $request->businessReference,
                'idempotency_key' => $request->idempotencyKey,
                'expires_at' => $request->expiresAt,
                'metadata' => $request->metadata === [] ? null : $request->metadata,
            ]);

            $posted = $this->ledger->post(new PostingRequest(
                journalCode: 'WALLET',
                type: 'VALUE_RESERVATION',
                sourceModule: 'wallet',
                businessReference: $request->businessReference,
                idempotencyKey: "reserve:{$request->idempotencyKey}",
                unit: $wallet->unit,
                currency: $wallet->currency,
                entries: [
                    new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Debit, $request->amountMinor, 'Valeur rendue indisponible'),
                    new PostingEntry($wallet->reserved_ledger_account_id, EntryDirection::Credit, $request->amountMinor, 'Valeur réservée'),
                ],
                actorAccountId: $request->actorAccountId,
                beneficiaryAccountId: $wallet->account_id,
                justification: "Réservation {$request->purpose}",
                ruleVersion: 'p003-reservation-v1',
                metadata: ['reservation_id' => $reservation->id, ...$request->metadata],
                traceId: $request->traceId,
            ));

            $reservation->forceFill(['reserve_ledger_transaction_id' => $posted->transactionId])->save();
            $this->recordOperation(
                wallet: $wallet, ledgerTransactionId: $posted->transactionId, type: 'reservation',
                direction: 'internal', amountMinor: $request->amountMinor,
                businessReference: $request->businessReference, idempotencyKey: "reserve:{$request->idempotencyKey}",
                description: 'Montant réservé', metadata: ['reservation_id' => $reservation->id],
            );
            $this->projector->rebuild($wallet, $posted->transactionId);
            DB::afterCommit(static fn () => event(new ValueReserved($reservation->id, $wallet->id, $request->amountMinor)));

            return $reservation;
        }, 5);
    }

    public function capture(
        string $reservationId,
        string $destinationLedgerAccountId,
        string $idempotencyKey,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): WalletReservation {
        return DB::transaction(function () use ($reservationId, $destinationLedgerAccountId, $idempotencyKey, $actorAccountId, $traceId): WalletReservation {
            $reservation = WalletReservation::query()->lockForUpdate()->findOrFail($reservationId);
            if ($reservation->status === ReservationStatus::Captured) {
                return $reservation;
            }
            if ($reservation->status !== ReservationStatus::Active) {
                throw new WalletException('Cette réservation ne peut plus être capturée.');
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($reservation->wallet_id);
            if ($wallet->reserved_ledger_account_id === null) {
                throw new WalletException('Le compartiment réservé du Wallet est absent.');
            }

            $posted = $this->ledger->post(new PostingRequest(
                journalCode: 'WALLET', type: 'VALUE_RESERVATION_CAPTURE', sourceModule: 'wallet',
                businessReference: $reservation->business_reference, idempotencyKey: "capture:{$idempotencyKey}",
                unit: $wallet->unit, currency: $wallet->currency,
                entries: [
                    new PostingEntry($wallet->reserved_ledger_account_id, EntryDirection::Debit, $reservation->amount_minor, 'Réservation consommée'),
                    new PostingEntry($destinationLedgerAccountId, EntryDirection::Credit, $reservation->amount_minor, 'Valeur capturée'),
                ],
                actorAccountId: $actorAccountId, beneficiaryAccountId: $wallet->account_id,
                justification: 'Capture d’une réservation Wallet', ruleVersion: 'p003-reservation-v1',
                metadata: ['reservation_id' => $reservation->id], traceId: $traceId,
            ));

            $reservation->forceFill([
                'status' => ReservationStatus::Captured,
                'resolution_ledger_transaction_id' => $posted->transactionId,
                'resolved_at' => now(),
            ])->save();
            $this->recordOperation(
                $wallet, $posted->transactionId, 'reservation_capture', 'debit', $reservation->amount_minor,
                $reservation->business_reference, "capture:{$idempotencyKey}", 'Réservation capturée', ['reservation_id' => $reservation->id],
            );
            $this->projector->rebuild($wallet, $posted->transactionId);
            DB::afterCommit(static fn () => event(new ValueReservationCaptured($reservation->id, $wallet->id, $reservation->amount_minor)));

            return $reservation;
        }, 5);
    }

    public function release(
        string $reservationId,
        string $idempotencyKey,
        string $reason,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): WalletReservation {
        return $this->resolveAsReleased(
            $reservationId, $idempotencyKey, $reason, ReservationStatus::Released, $actorAccountId, $traceId,
        );
    }

    public function expireDue(): int
    {
        $ids = WalletReservation::query()
            ->where('status', ReservationStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            $this->resolveAsReleased(
                (string) $id, "expire:{$id}", 'Expiration automatique', ReservationStatus::Expired,
            );
            $expired++;
        }

        return $expired;
    }

    private function resolveAsReleased(
        string $reservationId,
        string $idempotencyKey,
        string $reason,
        ReservationStatus $finalStatus,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): WalletReservation {
        if (trim($reason) === '') {
            throw new WalletException('Une libération exige un motif.');
        }

        return DB::transaction(function () use ($reservationId, $idempotencyKey, $reason, $finalStatus, $actorAccountId, $traceId): WalletReservation {
            $reservation = WalletReservation::query()->lockForUpdate()->findOrFail($reservationId);
            if ($reservation->status === $finalStatus) {
                return $reservation;
            }
            if ($reservation->status !== ReservationStatus::Active) {
                throw new WalletException('Cette réservation ne peut plus être libérée.');
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($reservation->wallet_id);
            if ($wallet->reserved_ledger_account_id === null || $wallet->available_ledger_account_id === null) {
                throw new WalletException('Les compartiments du Wallet sont absents.');
            }

            $posted = $this->ledger->post(new PostingRequest(
                journalCode: 'WALLET', type: $finalStatus === ReservationStatus::Expired ? 'VALUE_RESERVATION_EXPIRE' : 'VALUE_RESERVATION_RELEASE',
                sourceModule: 'wallet', businessReference: $reservation->business_reference,
                idempotencyKey: "release:{$idempotencyKey}", unit: $wallet->unit, currency: $wallet->currency,
                entries: [
                    new PostingEntry($wallet->reserved_ledger_account_id, EntryDirection::Debit, $reservation->amount_minor, 'Réservation libérée'),
                    new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $reservation->amount_minor, 'Disponible restauré'),
                ],
                actorAccountId: $actorAccountId, beneficiaryAccountId: $wallet->account_id,
                justification: $reason, ruleVersion: 'p003-reservation-v1',
                metadata: ['reservation_id' => $reservation->id, 'resolution' => $finalStatus->value], traceId: $traceId,
            ));

            $reservation->forceFill([
                'status' => $finalStatus,
                'resolution_ledger_transaction_id' => $posted->transactionId,
                'resolved_at' => now(),
            ])->save();
            $this->recordOperation(
                $wallet, $posted->transactionId, $finalStatus === ReservationStatus::Expired ? 'reservation_expiration' : 'reservation_release',
                'internal', $reservation->amount_minor, $reservation->business_reference, "release:{$idempotencyKey}",
                $finalStatus === ReservationStatus::Expired ? 'Réservation expirée' : 'Réservation libérée',
                ['reservation_id' => $reservation->id, 'reason' => $reason],
            );
            $this->projector->rebuild($wallet, $posted->transactionId);
            DB::afterCommit(static fn () => event(new ValueReservationReleased(
                $reservation->id, $wallet->id, $reservation->amount_minor, $reason,
            )));

            return $reservation;
        }, 5);
    }

    /** @param array<string, mixed> $metadata */
    private function recordOperation(
        Wallet $wallet,
        string $ledgerTransactionId,
        string $type,
        string $direction,
        int $amountMinor,
        string $businessReference,
        string $idempotencyKey,
        string $description,
        array $metadata,
    ): void {
        WalletOperation::query()->firstOrCreate(
            ['ledger_transaction_id' => $ledgerTransactionId],
            [
                'wallet_id' => $wallet->id,
                'type' => $type,
                'direction' => $direction,
                'status' => 'posted',
                'amount_minor' => $amountMinor,
                'unit' => $wallet->unit,
                'currency' => $wallet->currency,
                'business_reference' => $businessReference,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ],
        );
    }
}
