<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Domain\ValueObjects\CreateDepositRequest;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use Illuminate\Support\Str;

/**
 * docs/13-studio-annonceur-wasplex.md §26: "Recharger → montant → moyen de
 * paiement → référence → paiement → confirmation prestataire → Grand Livre
 * → solde disponible". This service only covers up to "référence" — the
 * credit itself only ever happens from a verified webhook
 * (GeniusPayWebhookHandler), never from this synchronous request.
 */
final class DepositService
{
    public function __construct(private readonly PaymentProviderContract $provider) {}

    public function createDeposit(string $organizationId, string $initiatedBy, int $amountMinor, string $currency): AdvertiserWalletDeposit
    {
        $deposit = AdvertiserWalletDeposit::query()->create([
            'organization_id' => $organizationId,
            'initiated_by' => $initiatedBy,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => AdvertiserWalletDeposit::STATUS_CREATED,
            'provider_code' => 'geniuspay',
            'idempotency_key' => (string) Str::ulid(),
        ]);

        // Deliberately outside any DB transaction (docs/20-architecture-
        // technique-generale-wasplex.md #43: no long external call inside
        // an open PostgreSQL transaction). If this throws, the deposit row
        // stays 'created' with no checkout URL — visible in history as a
        // failed initiation, safely retryable via a new deposit.
        $result = $this->provider->createDeposit(new CreateDepositRequest(
            amountMinor: $amountMinor,
            currency: $currency,
            internalReference: $deposit->id,
            idempotencyKey: $deposit->idempotency_key,
        ));

        $deposit->update([
            'status' => $this->mapProviderStatusToDepositStatus($result->normalizedStatus),
            'provider_reference' => $result->providerReference,
            'checkout_url' => $result->checkoutUrl,
            'metadata' => ['environment' => $result->environment],
        ]);

        return $deposit->refresh();
    }

    private function mapProviderStatusToDepositStatus(string $normalizedStatus): string
    {
        return match ($normalizedStatus) {
            'awaiting_payment' => AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT,
            'confirmed' => AdvertiserWalletDeposit::STATUS_CONFIRMED,
            'rejected' => AdvertiserWalletDeposit::STATUS_REJECTED,
            'expired' => AdvertiserWalletDeposit::STATUS_EXPIRED,
            default => AdvertiserWalletDeposit::STATUS_CREATED,
        };
    }
}
