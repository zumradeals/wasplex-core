<?php

namespace App\Modules\Wallet\Application\Contracts;

use App\Modules\Wallet\Application\Data\ReservationRequest;
use App\Modules\Wallet\Infrastructure\Models\WalletReservation;

interface WalletReservationContract
{
    public function reserve(ReservationRequest $request): WalletReservation;

    public function capture(
        string $reservationId,
        string $destinationLedgerAccountId,
        string $idempotencyKey,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): WalletReservation;

    public function release(
        string $reservationId,
        string $idempotencyKey,
        string $reason,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): WalletReservation;

    public function expireDue(): int;
}
