<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Domain\ValueObjects\CreateDepositRequest;
use App\Modules\AdvertiserWallet\Domain\ValueObjects\ProviderDepositResult;
use App\Modules\AdvertiserWallet\Domain\ValueObjects\ProviderWebhookEvent;

/**
 * Internal contract every payment provider adapter implements
 * (docs/19-integrations-externes-wasplex.md §6: "les modules métier
 * utilisent ces contrats, jamais les SDK fournisseurs directement"). Only
 * GeniusPay is implemented in P003; this interface is what a second
 * provider (Mobile Money, virement…) would implement later without
 * changing any caller.
 */
interface PaymentProviderContract
{
    public function createDeposit(CreateDepositRequest $request): ProviderDepositResult;

    public function verifyWebhookSignature(string $rawPayload, array $headers): bool;

    public function parseWebhookPayload(string $rawPayload): ProviderWebhookEvent;

    /**
     * Server-side re-verification: the deposit is never credited from the
     * webhook payload alone or from a browser redirect — this looks the
     * status up directly from the provider (docs/chantiers/
     * HOTFIX-P003-GENIUSPAY-SANDBOX.md §4: "crédit uniquement après webhook
     * signé et revérification serveur").
     */
    public function fetchDepositStatus(string $providerReference): ProviderDepositResult;
}
