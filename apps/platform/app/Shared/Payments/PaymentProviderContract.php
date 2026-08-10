<?php

declare(strict_types=1);

namespace App\Shared\Payments;

use App\Shared\Payments\ValueObjects\CreatePaymentRequest;
use App\Shared\Payments\ValueObjects\ProviderPaymentResult;
use App\Shared\Payments\ValueObjects\ProviderWebhookEvent;

/**
 * Internal contract every payment provider adapter implements
 * (docs/19-integrations-externes-wasplex.md §6: "les modules métier
 * utilisent ces contrats, jamais les SDK fournisseurs directement").
 * Deliberately not owned by a single business module: it is a technical
 * integration concern (CLAUDE.md §10), shared by AdvertiserWallet (P003)
 * and Subscriptions (P004) alike — each module keeps its own webhook
 * route, its own payment table and its own reconciliation/activation
 * logic, only the provider integration itself is shared. Only GeniusPay is
 * implemented; this interface is what a second provider (Mobile Money,
 * virement…) would implement later without changing any caller.
 */
interface PaymentProviderContract
{
    /** @return array<string, mixed> */
    public function testConnection(): array;

    public function createPayment(CreatePaymentRequest $request): ProviderPaymentResult;

    public function verifyWebhookSignature(string $rawPayload, array $headers): bool;

    public function parseWebhookPayload(string $rawPayload): ProviderWebhookEvent;

    /**
     * Server-side re-verification: a payment is never confirmed from the
     * webhook payload alone or from a browser redirect — this looks the
     * status up directly from the provider (docs/chantiers/
     * HOTFIX-P003-GENIUSPAY-SANDBOX.md §4: "crédit uniquement après webhook
     * signé et revérification serveur").
     */
    public function fetchPaymentStatus(string $providerReference): ProviderPaymentResult;
}
