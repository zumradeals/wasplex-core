<?php

declare(strict_types=1);

namespace App\Modules\Funds\Application\Services;

use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use App\Modules\Funds\Infrastructure\Models\FundReserveAllocation;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class FundRealizationService
{
    public const RESERVE_ACCOUNT_CODE = 'fund.reserve.wp';

    private const RULE_VERSION = 'P014-E-v1';

    public function __construct(private readonly LedgerPostingContract $posting) {}

    public function createOrder(FundWish $wish, string $actorAccountId): FundOrder
    {
        return DB::transaction(function () use ($wish, $actorAccountId): FundOrder {
            $wish = FundWish::query()->with(['selectedQuote', 'collectionSnapshot'])->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if ($wish->provider_organization_id === null || $wish->selected_quote_id === null || $wish->validated_amount_minor === null) {
                throw new RuntimeException('Un prestataire et un coût validé sont requis avant la commande.');
            }
            $snapshot = $wish->collectionSnapshot;
            if (! $snapshot instanceof FundCollectionSnapshot || $snapshot->status !== FundCollectionSnapshot::STATUS_FUNDED) {
                throw new RuntimeException('La collecte doit être entièrement financée avant la commande.');
            }

            $authorizedReserve = (int) FundReserveAllocation::query()
                ->where('fund_wish_id', $wish->id)
                ->whereIn('status', [FundReserveAllocation::STATUS_AUTHORIZED, FundReserveAllocation::STATUS_PARTIALLY_CONSUMED])
                ->get()
                ->sum(fn (FundReserveAllocation $allocation): int => max(0, (int) $allocation->amount_minor - (int) $allocation->consumed_minor));

            $order = FundOrder::query()->firstOrCreate(['fund_wish_id' => $wish->id], [
                'quote_id' => $wish->selected_quote_id,
                'provider_organization_id' => $wish->provider_organization_id,
                'status' => FundOrder::STATUS_ISSUED,
                'total_minor' => (int) $wish->validated_amount_minor,
                'currency' => $wish->currency,
                'authorized_reserve_minor' => $authorizedReserve,
                'ordered_by_account_id' => $actorAccountId,
                'ordered_at' => now(),
            ]);
            FundReserveAllocation::query()
                ->where('fund_wish_id', $wish->id)
                ->whereNull('fund_order_id')
                ->update(['fund_order_id' => $order->id]);

            return $order;
        });
    }

    public function addMilestone(FundOrder $order, string $label, int $amountMinor, bool $proofRequired, ?string $dueAt): array
    {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Le montant du jalon doit être positif.');
        }

        return DB::transaction(function () use ($order, $label, $amountMinor, $proofRequired, $dueAt): array {
            $order = FundOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, [FundOrder::STATUS_COMPLETED, FundOrder::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Cette commande est clôturée.');
            }
            $allocated = (int) DB::table('fund_order_milestones')->where('fund_order_id', $order->id)->sum('amount_minor');
            if ($allocated + $amountMinor > (int) $order->total_minor + (int) $order->authorized_reserve_minor) {
                throw new RuntimeException('Les jalons dépassent le financement autorisé.');
            }
            $ordinal = ((int) DB::table('fund_order_milestones')->where('fund_order_id', $order->id)->max('ordinal')) + 1;
            $id = (string) Str::ulid();
            DB::table('fund_order_milestones')->insert([
                'id' => $id,
                'fund_order_id' => $order->id,
                'ordinal' => $ordinal,
                'label' => $label,
                'amount_minor' => $amountMinor,
                'status' => 'pending',
                'proof_required' => $proofRequired,
                'due_at' => $dueAt,
                'external_settlement_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('fund_order_milestones')->where('id', $id)->first();
        });
    }

    public function submitProof(FundOrder $order, string $organizationId, string $actorAccountId, ?string $milestoneId, string $type, ?string $reference, ?string $description): array
    {
        if ((string) $order->provider_organization_id !== $organizationId) {
            throw new RuntimeException('Commande introuvable.');
        }
        if ($milestoneId !== null && ! DB::table('fund_order_milestones')->where('id', $milestoneId)->where('fund_order_id', $order->id)->exists()) {
            throw new RuntimeException('Jalon introuvable.');
        }
        $id = (string) Str::ulid();
        DB::transaction(function () use ($id, $order, $organizationId, $actorAccountId, $milestoneId, $type, $reference, $description): void {
            DB::table('fund_order_proofs')->insert([
                'id' => $id,
                'fund_order_id' => $order->id,
                'fund_order_milestone_id' => $milestoneId,
                'organization_id' => $organizationId,
                'submitted_by_account_id' => $actorAccountId,
                'proof_type' => $type,
                'reference' => $reference,
                'description' => $description,
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($milestoneId !== null) {
                DB::table('fund_order_milestones')->where('id', $milestoneId)->update([
                    'status' => 'proof_submitted',
                    'submitted_at' => now(),
                    'submitted_by_account_id' => $actorAccountId,
                    'updated_at' => now(),
                ]);
            }
        });

        return (array) DB::table('fund_order_proofs')->where('id', $id)->first();
    }

    public function approveMilestone(FundOrder $order, string $milestoneId, string $actorAccountId): array
    {
        return DB::transaction(function () use ($order, $milestoneId, $actorAccountId): array {
            $milestone = DB::table('fund_order_milestones')->where('id', $milestoneId)->where('fund_order_id', $order->id)->lockForUpdate()->first();
            if ($milestone === null) {
                throw new RuntimeException('Jalon introuvable.');
            }
            if ((bool) $milestone->proof_required && ! DB::table('fund_order_proofs')->where('fund_order_milestone_id', $milestoneId)->exists()) {
                throw new RuntimeException('Une preuve est requise avant validation.');
            }
            DB::table('fund_order_milestones')->where('id', $milestoneId)->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_account_id' => $actorAccountId,
                'updated_at' => now(),
            ]);

            return (array) DB::table('fund_order_milestones')->where('id', $milestoneId)->first();
        });
    }

    public function postMilestoneToLedger(FundOrder $order, string $milestoneId, string $actorAccountId): array
    {
        return DB::transaction(function () use ($order, $milestoneId, $actorAccountId): array {
            $order = FundOrder::query()->with('wish.collectionSnapshot')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $milestone = DB::table('fund_order_milestones')->where('id', $milestoneId)->where('fund_order_id', $order->id)->lockForUpdate()->first();
            if ($milestone === null) {
                throw new RuntimeException('Jalon introuvable.');
            }
            if ($milestone->ledger_transaction_id !== null) {
                return (array) $milestone;
            }
            if ($milestone->status !== 'approved') {
                throw new RuntimeException('Le jalon doit être validé avant allocation.');
            }

            $amount = (int) $milestone->amount_minor;
            $wishRef = LedgerAccountReference::forIdentityAccount('fund.wish.personal.'.$order->fund_wish_id.'.wp', (string) $order->wish->account_id, UserWalletQueryService::ACCOUNT_TYPE_CODE, 'WP');
            $poolRef = LedgerAccountReference::system('fund.collection.pool.'.$order->wish->collectionSnapshot->id.'.wp', 'FONDS', 'WP');
            $reserveRef = LedgerAccountReference::system(self::RESERVE_ACCOUNT_CODE, 'FONDS', 'WP');
            $providerRef = LedgerAccountReference::system('fund.provider.payable.'.$order->provider_organization_id.'.wp', 'FONDS', 'WP');

            $personal = min($amount, $this->balance($wishRef));
            $remaining = $amount - $personal;
            $collective = min($remaining, $this->balance($poolRef));
            $remaining -= $collective;
            $reserve = 0;
            if ($remaining > 0) {
                $alreadyReserve = (int) DB::table('fund_reserve_entries')->where('fund_order_id', $order->id)->where('direction', 'debit')->sum('amount_minor');
                $reserveAllowance = max(0, (int) $order->authorized_reserve_minor - $alreadyReserve);
                $reserve = min($remaining, $reserveAllowance, $this->balance($reserveRef));
                $remaining -= $reserve;
            }
            if ($remaining > 0) {
                throw new RuntimeException('Financement disponible insuffisant pour ce jalon.');
            }

            $entries = [];
            if ($personal > 0) {
                $entries[] = LedgerEntryInput::debit($wishRef, Money::of($personal, 'WP'), 'Apport personnel affecté au prestataire Fonds');
            }
            if ($collective > 0) {
                $entries[] = LedgerEntryInput::debit($poolRef, Money::of($collective, 'WP'), 'Solidarité affectée au prestataire Fonds');
            }
            if ($reserve > 0) {
                $entries[] = LedgerEntryInput::debit($reserveRef, Money::of($reserve, 'WP'), 'Réserve Fonds affectée à la réalisation');
            }
            $entries[] = LedgerEntryInput::credit($providerRef, Money::of($amount, 'WP'), 'Montant à régler au prestataire Fonds');

            $tx = $this->posting->post(new PostLedgerTransaction(
                type: 'FUND_PROVIDER_MILESTONE_ALLOCATION',
                sourceModule: 'funds',
                idempotencyKey: 'fund-order:'.$order->id.':milestone:'.$milestoneId,
                entries: $entries,
                businessReference: 'fund-order:'.$order->id,
                createdBy: $actorAccountId,
                metadata: ['order_id' => $order->id, 'milestone_id' => $milestoneId, 'provider_organization_id' => $order->provider_organization_id],
                ruleVersion: self::RULE_VERSION,
            ));

            DB::table('fund_order_milestones')->where('id', $milestoneId)->update([
                'status' => 'ledger_posted',
                'ledger_transaction_id' => $tx->id,
                'ledger_posted_at' => now(),
                'updated_at' => now(),
            ]);
            $order->increment('ledger_allocated_minor', $amount);
            if ($order->status === FundOrder::STATUS_ISSUED) {
                $order->update(['status' => FundOrder::STATUS_IN_PROGRESS]);
            }
            if ($reserve > 0) {
                DB::table('fund_reserve_entries')->insert([
                    'id' => (string) Str::ulid(), 'fund_order_id' => $order->id, 'direction' => 'debit', 'amount_minor' => $reserve,
                    'reason' => 'Complément exceptionnel de réalisation', 'ledger_transaction_id' => $tx->id, 'created_by_account_id' => $actorAccountId, 'created_at' => now(),
                ]);
                $this->consumeReserveAllocations($order, $reserve);
            }

            return (array) DB::table('fund_order_milestones')->where('id', $milestoneId)->first();
        });
    }

    public function confirmExternalSettlement(FundOrder $order, string $milestoneId, string $reference): array
    {
        if (trim($reference) === '') {
            throw new RuntimeException('Une référence de règlement externe est obligatoire.');
        }

        return DB::transaction(function () use ($order, $milestoneId, $reference): array {
            $order = FundOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $milestone = DB::table('fund_order_milestones')->where('id', $milestoneId)->where('fund_order_id', $order->id)->lockForUpdate()->first();
            if ($milestone === null || $milestone->ledger_transaction_id === null) {
                throw new RuntimeException('L’allocation Ledger doit exister avant confirmation du règlement externe.');
            }
            if ($milestone->external_settlement_status === 'confirmed') {
                return (array) $milestone;
            }
            DB::table('fund_order_milestones')->where('id', $milestoneId)->update([
                'status' => 'settled', 'external_settlement_status' => 'confirmed', 'external_reference' => $reference,
                'externally_settled_at' => now(), 'updated_at' => now(),
            ]);
            $order->increment('externally_settled_minor', (int) $milestone->amount_minor);
            $this->refreshCompletion($order->fresh());

            return (array) DB::table('fund_order_milestones')->where('id', $milestoneId)->first();
        });
    }

    public function authorizeReserve(FundOrder $order, int $amountMinor): FundOrder
    {
        throw new RuntimeException('La réserve Fonds doit être autorisée avant le snapshot de collecte depuis le pilotage Fonds.');
    }

    public function markDelivered(FundOrder $order, string $organizationId, string $actorAccountId, string $reference, ?string $description): FundOrder
    {
        if ((string) $order->provider_organization_id !== $organizationId) {
            throw new RuntimeException('Commande introuvable.');
        }
        $this->submitProof($order, $organizationId, $actorAccountId, null, 'delivery', $reference, $description);
        $order->update(['status' => FundOrder::STATUS_DELIVERED, 'delivered_at' => now()]);

        return $order->refresh();
    }

    public function confirmBeneficiary(FundOrder $order, string $accountId): FundOrder
    {
        $order->load('wish');
        if ((string) $order->wish->account_id !== $accountId) {
            throw new RuntimeException('Commande introuvable.');
        }
        if ($order->delivered_at === null) {
            throw new RuntimeException('La livraison doit être déclarée avant confirmation.');
        }
        $order->update(['beneficiary_confirmed_at' => now()]);
        $this->refreshCompletion($order->fresh());

        return $order->refresh();
    }

    public function openDispute(FundOrder $order, string $accountId, string $reason): array
    {
        $order->load('wish');
        if ((string) $order->wish->account_id !== $accountId) {
            throw new RuntimeException('Commande introuvable.');
        }
        if (DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->where('status', 'open')->exists()) {
            throw new RuntimeException('Un litige est déjà ouvert.');
        }
        $id = (string) Str::ulid();
        DB::table('fund_order_disputes')->insert([
            'id' => $id, 'fund_order_id' => $order->id, 'opened_by_account_id' => $accountId, 'status' => 'open', 'reason' => $reason,
            'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $order->update(['status' => FundOrder::STATUS_DISPUTED]);

        return (array) DB::table('fund_order_disputes')->where('id', $id)->first();
    }

    public function resolveDispute(FundOrder $order, string $disputeId, string $actorAccountId, string $resolution, string $note): FundOrder
    {
        return DB::transaction(function () use ($order, $disputeId, $actorAccountId, $resolution, $note): FundOrder {
            $dispute = DB::table('fund_order_disputes')->where('id', $disputeId)->where('fund_order_id', $order->id)->where('status', 'open')->lockForUpdate()->first();
            if ($dispute === null) {
                throw new RuntimeException('Litige ouvert introuvable.');
            }
            DB::table('fund_order_disputes')->where('id', $disputeId)->update([
                'status' => 'resolved', 'resolution_code' => $resolution, 'resolution_note' => $note,
                'resolved_by_account_id' => $actorAccountId, 'resolved_at' => now(), 'updated_at' => now(),
            ]);
            $status = match ($resolution) {
                'accept_delivery' => FundOrder::STATUS_DELIVERED,
                'resume' => FundOrder::STATUS_IN_PROGRESS,
                'cancel' => FundOrder::STATUS_CANCELLED,
                default => throw new RuntimeException('Résolution inconnue.'),
            };
            $order->update(['status' => $status, 'cancelled_at' => $status === FundOrder::STATUS_CANCELLED ? now() : null]);
            $this->refreshCompletion($order->fresh());

            return $order->refresh();
        });
    }

    private function consumeReserveAllocations(FundOrder $order, int $amountMinor): void
    {
        $remaining = $amountMinor;
        $allocations = FundReserveAllocation::query()
            ->where('fund_order_id', $order->id)
            ->whereIn('status', [FundReserveAllocation::STATUS_AUTHORIZED, FundReserveAllocation::STATUS_PARTIALLY_CONSUMED])
            ->orderBy('authorized_at')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }
            $available = max(0, (int) $allocation->amount_minor - (int) $allocation->consumed_minor);
            $consume = min($available, $remaining);
            if ($consume <= 0) {
                continue;
            }
            $consumed = (int) $allocation->consumed_minor + $consume;
            $allocation->update([
                'consumed_minor' => $consumed,
                'status' => $consumed >= (int) $allocation->amount_minor
                    ? FundReserveAllocation::STATUS_CONSUMED
                    : FundReserveAllocation::STATUS_PARTIALLY_CONSUMED,
                'consumed_at' => $consumed >= (int) $allocation->amount_minor ? now() : null,
            ]);
            $remaining -= $consume;
        }

        if ($remaining > 0) {
            throw new RuntimeException('La consommation de réserve dépasse les allocations autorisées.');
        }
    }

    public function reserveBalanceMinor(): int
    {
        return $this->balance(LedgerAccountReference::system(self::RESERVE_ACCOUNT_CODE, 'FONDS', 'WP'));
    }

    private function refreshCompletion(FundOrder $order): void
    {
        $hasOpenDispute = DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->where('status', 'open')->exists();
        $milestones = DB::table('fund_order_milestones')->where('fund_order_id', $order->id)->get();
        $allSettled = $milestones->isNotEmpty() && $milestones->every(fn ($m): bool => $m->external_settlement_status === 'confirmed');
        if (! $hasOpenDispute && $order->beneficiary_confirmed_at !== null && $allSettled) {
            $order->update(['status' => FundOrder::STATUS_COMPLETED, 'completed_at' => now()]);
        }
    }

    private function balance(LedgerAccountReference $reference): int
    {
        $account = LedgerAccount::query()->where('code', $reference->code)->where('owner_type', $reference->ownerType)->where('owner_id', $reference->ownerId)->first();
        if ($account === null) {
            return 0;
        }
        $credits = (int) LedgerEntry::query()->where('account_id', $account->id)->where('direction', LedgerEntry::DIRECTION_CREDIT)->sum('amount_minor');
        $debits = (int) LedgerEntry::query()->where('account_id', $account->id)->where('direction', LedgerEntry::DIRECTION_DEBIT)->sum('amount_minor');

        return $credits - $debits;
    }
}
