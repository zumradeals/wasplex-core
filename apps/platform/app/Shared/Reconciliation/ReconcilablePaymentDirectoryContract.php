<?php

declare(strict_types=1);

namespace App\Shared\Reconciliation;

/**
 * Implemented once per source module that initiates GeniusPay payments
 * (AdvertiserWallet, Subscriptions). Each implementation is tagged
 * 'reconciliation.directories' in its own module's ServiceProvider — the
 * Reconciliation module never references a source module's concrete class,
 * only this shared contract (docs/chantiers/P011-B-RAPPROCHEMENT.md §4).
 */
interface ReconcilablePaymentDirectoryContract
{
    public function sourceModule(): string;

    /**
     * @return array<int, ReconcilablePaymentRecord>
     */
    public function pending(): array;
}
