<?php

declare(strict_types=1);

namespace App\Shared\Payments\ValueObjects;

/**
 * Normalized result of creating or looking up a payment with the provider
 * (docs/19-integrations-externes-wasplex.md §14-15: "statuts normalisés" /
 * "mapping des statuts"). $providerStatusRaw may be null — GeniusPay's real
 * sandbox response returns `status: null` on a successful creation
 * (docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md), which the adapter
 * normalizes to `awaiting_payment` only when a non-empty checkout URL is
 * also present.
 */
final class ProviderPaymentResult
{
    public function __construct(
        public readonly ?string $providerReference,
        public readonly ?string $checkoutUrl,
        public readonly ?string $providerStatusRaw,
        public readonly string $normalizedStatus,
        public readonly string $environment,
        // Echoed back from the provider's own record of the transaction —
        // same field names as the webhook payload's data.amount/data.currency
        // (docs/chantiers/P011-B-RAPPROCHEMENT.md §4: assumption not yet
        // re-confirmed against real, non-sandboxed GeniusPay traffic).
        // Null when the provider response omits them (e.g. reference
        // not found).
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
    ) {}
}
