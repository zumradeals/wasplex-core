<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use Illuminate\Support\Str;

/**
 * docs/13-studio-annonceur-wasplex.md §28: "un administrateur peut
 * approuver un dépôt manuel après vérification" — preuve, référence,
 * motif, idempotence, Grand Livre, audit, "aucun crédit direct du solde".
 * Reuses advertiser_wallet_deposits (P003) with provider_code =
 * 'manual_supervised' instead of a new table: the lifecycle (awaiting
 * confirmation -> credited/rejected) is identical, only the confirmation
 * source differs (an admin's approval instead of a GeniusPay webhook).
 *
 * Two-person rule: the admin who proposes a supervised deposit can never
 * be the one who approves it — same invariant as Ledger's correction
 * workflow (P002), applied here because this, too, is a manual override
 * of the normal automated crediting path.
 */
final class SupervisedDepositService
{
    public function __construct(private readonly AdvertiserWalletCreditor $creditor) {}

    public function create(string $organizationId, int $amountMinor, string $currency, string $proofReference, string $reason, string $requestedBy): AdvertiserWalletDeposit
    {
        return AdvertiserWalletDeposit::query()->create([
            'organization_id' => $organizationId,
            'initiated_by' => $requestedBy,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT,
            'provider_code' => 'manual_supervised',
            'provider_reference' => $proofReference,
            'idempotency_key' => (string) Str::ulid(),
            'metadata' => ['reason' => $reason, 'requested_by' => $requestedBy],
        ]);
    }

    public function approve(AdvertiserWalletDeposit $deposit, string $approvedBy): AdvertiserWalletDeposit
    {
        if ($deposit->isTerminal()) {
            throw new SupervisedDepositAlreadyDecidedException($deposit->id);
        }

        $requestedBy = (string) ($deposit->metadata['requested_by'] ?? $deposit->initiated_by);
        if ($requestedBy === $approvedBy) {
            throw new SupervisedDepositSelfApprovalException($deposit->id);
        }

        return $this->creditor->credit($deposit, "Dépôt supervisé {$deposit->provider_reference}");
    }

    public function reject(AdvertiserWalletDeposit $deposit, string $rejectedBy, string $reason): AdvertiserWalletDeposit
    {
        if ($deposit->isTerminal()) {
            throw new SupervisedDepositAlreadyDecidedException($deposit->id);
        }

        $deposit->update([
            'status' => AdvertiserWalletDeposit::STATUS_REJECTED,
            'metadata' => [...$deposit->metadata, 'rejected_by' => $rejectedBy, 'rejection_reason' => $reason],
        ]);

        return $deposit->refresh();
    }
}
