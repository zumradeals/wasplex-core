<?php

namespace App\Modules\Wallet\Application\Data;

use App\Modules\Wallet\Domain\Exceptions\WalletException;
use DateTimeImmutable;

final readonly class ReservationRequest
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $walletId,
        public string $purpose,
        public int $amountMinor,
        public string $businessReference,
        public string $idempotencyKey,
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $actorAccountId = null,
        public array $metadata = [],
        public ?string $traceId = null,
    ) {
        if ($this->amountMinor <= 0) {
            throw new WalletException('Le montant à réserver doit être strictement positif.');
        }

        foreach (['walletId', 'purpose', 'businessReference', 'idempotencyKey'] as $field) {
            if (trim($this->{$field}) === '') {
                throw new WalletException("Le champ {$field} est obligatoire.");
            }
        }
    }
}
