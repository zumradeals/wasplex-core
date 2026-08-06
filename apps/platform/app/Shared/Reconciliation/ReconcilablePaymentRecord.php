<?php

declare(strict_types=1);

namespace App\Shared\Reconciliation;

use Illuminate\Support\Carbon;

/**
 * Minimal by-value projection a source module (AdvertiserWallet,
 * Subscriptions) exposes to Reconciliation — never the module's own
 * Eloquent model (docs/CLAUDE.md §6: no cross-module table reads).
 */
final class ReconcilablePaymentRecord
{
    public function __construct(
        public readonly string $sourceModule,
        public readonly string $sourceRecordId,
        public readonly ?string $providerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $internalStatus,
        public readonly ?string $ledgerTransactionId,
        public readonly int $webhookEventCount,
        public readonly Carbon $createdAt,
    ) {}
}
