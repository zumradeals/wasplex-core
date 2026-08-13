<?php

declare(strict_types=1);

namespace App\Shared\Payments\ValueObjects;

/**
 * Requête de création de paiement indépendante du fournisseur.
 *
 * Les URL de retour et les métadonnées appartiennent au contexte métier :
 * un abonnement utilisateur ne doit jamais hériter du retour Studio d'un
 * dépôt annonceur, et inversement.
 *
 * @param  array<string, mixed>|null  $metadata
 */
final class CreatePaymentRequest
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $internalReference,
        public readonly string $idempotencyKey,
        public readonly ?string $successUrl = null,
        public readonly ?string $errorUrl = null,
        public readonly ?string $description = null,
        public readonly ?array $metadata = null,
    ) {}
}
