<?php

declare(strict_types=1);

namespace App\Modules\Funds\Application\Services;

use App\Modules\Funds\Infrastructure\Models\FundPersonalContribution;
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
use RuntimeException;

final class FundContributionService
{
    public const FUND_BALANCE_ACCOUNT_CODE = 'fund.balance.wp';

    private const WISH_ACCOUNT_PREFIX = 'fund.wish.personal.';

    private const ACCOUNT_TYPE_CODE = 'LIABILITY_USER';

    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly UserWalletQueryService $wallet,
    ) {}

    public function fundBalanceMinor(string $accountId): int
    {
        return $this->balance($this->fundBalanceReference($accountId));
    }

    public function wishContributionMinor(FundWish $wish): int
    {
        return $this->balance($this->wishReference($wish));
    }

    public function transferWalletToFundBalance(
        string $accountId,
        int $amountMinor,
        string $idempotencyKey,
        ?string $createdBy = null,
    ): string {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Le montant doit être supérieur à zéro.');
        }

        $ledgerTransactionId = DB::transaction(function () use ($accountId, $amountMinor, $idempotencyKey, $createdBy): string {
            $this->lockAccountFlow($accountId);

            if ($this->wallet->balanceMinor($accountId) < $amountMinor) {
                throw new RuntimeException('Solde Wallet disponible insuffisant.');
            }

            $transaction = $this->posting->post(new PostLedgerTransaction(
                type: 'FUND_BALANCE_TRANSFER',
                sourceModule: 'funds',
                idempotencyKey: 'fund-balance:'.$accountId.':'.$idempotencyKey,
                entries: [
                    LedgerEntryInput::debit(
                        $this->wallet->availableAccountReference($accountId),
                        Money::of($amountMinor, 'WP'),
                        'Transfert vers le Solde Fonds',
                    ),
                    LedgerEntryInput::credit(
                        $this->fundBalanceReference($accountId),
                        Money::of($amountMinor, 'WP'),
                        'Alimentation du Solde Fonds',
                    ),
                ],
                businessReference: 'fund-balance:'.$accountId,
                createdBy: $createdBy,
                metadata: ['account_id' => $accountId],
                ruleVersion: 'P014-B-v1',
            ));

            return (string) $transaction->id;
        });

        $this->wallet->notifyBalanceChanged(
            $accountId,
            -$amountMinor,
            'funds',
            'fund_balance_transfer',
            $ledgerTransactionId,
        );

        return $ledgerTransactionId;
    }

    public function contributeToWish(
        string $accountId,
        FundWish $wish,
        int $amountMinor,
        string $source,
        string $idempotencyKey,
        ?string $createdBy = null,
    ): FundPersonalContribution {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Le montant doit être supérieur à zéro.');
        }

        if (! in_array($source, [FundPersonalContribution::SOURCE_WALLET, FundPersonalContribution::SOURCE_FUND_BALANCE], true)) {
            throw new RuntimeException('Source d’apport non prise en charge.');
        }

        $existing = FundPersonalContribution::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if ($existing->fund_wish_id !== $wish->id || $existing->account_id !== $accountId || (int) $existing->amount_minor !== $amountMinor || $existing->source !== $source) {
                throw new RuntimeException('Cette clé d’idempotence a déjà été utilisée avec une autre opération.');
            }

            return $existing;
        }

        return DB::transaction(function () use ($accountId, $wish, $amountMinor, $source, $idempotencyKey, $createdBy): FundPersonalContribution {
            $this->lockAccountFlow($accountId);
            $wish = FundWish::query()->whereKey($wish->id)->lockForUpdate()->firstOrFail();

            $required = (int) ($wish->required_personal_contribution_minor ?? 0);
            if ($required <= 0) {
                throw new RuntimeException('L’apport personnel requis doit être défini avant tout versement.');
            }

            $alreadyContributed = $this->wishContributionMinor($wish);
            $remaining = max(0, $required - $alreadyContributed);
            if ($remaining === 0) {
                throw new RuntimeException('L’apport personnel de ce vœu est déjà complet.');
            }
            if ($amountMinor > $remaining) {
                throw new RuntimeException('Le versement dépasse le montant restant de l’apport personnel.');
            }

            $sourceReference = $source === FundPersonalContribution::SOURCE_WALLET
                ? $this->wallet->availableAccountReference($accountId)
                : $this->fundBalanceReference($accountId);

            $sourceBalance = $source === FundPersonalContribution::SOURCE_WALLET
                ? $this->wallet->balanceMinor($accountId)
                : $this->fundBalanceMinor($accountId);

            if ($sourceBalance < $amountMinor) {
                throw new RuntimeException($source === FundPersonalContribution::SOURCE_WALLET
                    ? 'Solde Wallet disponible insuffisant.'
                    : 'Solde Fonds insuffisant.');
            }

            $transaction = $this->posting->post(new PostLedgerTransaction(
                type: 'FUND_WISH_PERSONAL_CONTRIBUTION',
                sourceModule: 'funds',
                idempotencyKey: 'fund-wish:'.$wish->id.':'.$idempotencyKey,
                entries: [
                    LedgerEntryInput::debit($sourceReference, Money::of($amountMinor, 'WP'), 'Apport personnel au vœu'),
                    LedgerEntryInput::credit($this->wishReference($wish), Money::of($amountMinor, 'WP'), 'Apport personnel réservé au vœu'),
                ],
                businessReference: 'fund-wish:'.$wish->id,
                createdBy: $createdBy,
                metadata: [
                    'fund_wish_id' => $wish->id,
                    'account_id' => $accountId,
                    'source' => $source,
                ],
                ruleVersion: 'P014-B-v1',
            ));

            $contribution = FundPersonalContribution::query()->create([
                'fund_wish_id' => $wish->id,
                'account_id' => $accountId,
                'source' => $source,
                'amount_minor' => $amountMinor,
                'currency' => 'WP',
                'idempotency_key' => $idempotencyKey,
                'ledger_transaction_id' => $transaction->id,
                'created_at' => now(),
            ]);

            if ($alreadyContributed + $amountMinor >= $required && $wish->personal_contribution_completed_at === null) {
                $wish->update(['personal_contribution_completed_at' => now()]);
            }

            return $contribution;
        });
    }

    private function fundBalanceReference(string $accountId): LedgerAccountReference
    {
        return LedgerAccountReference::forIdentityAccount(
            self::FUND_BALANCE_ACCOUNT_CODE,
            $accountId,
            self::ACCOUNT_TYPE_CODE,
            'WP',
        );
    }

    private function wishReference(FundWish $wish): LedgerAccountReference
    {
        return LedgerAccountReference::forIdentityAccount(
            self::WISH_ACCOUNT_PREFIX.$wish->id.'.wp',
            (string) $wish->account_id,
            self::ACCOUNT_TYPE_CODE,
            'WP',
        );
    }

    private function balance(LedgerAccountReference $reference): int
    {
        $account = LedgerAccount::query()
            ->where('code', $reference->code)
            ->where('owner_type', $reference->ownerType)
            ->where('owner_id', $reference->ownerId)
            ->first();

        if ($account === null) {
            return 0;
        }

        $credits = (int) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', LedgerEntry::DIRECTION_CREDIT)
            ->sum('amount_minor');
        $debits = (int) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', LedgerEntry::DIRECTION_DEBIT)
            ->sum('amount_minor');

        return $credits - $debits;
    }

    private function lockAccountFlow(string $accountId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:'.$accountId]);
    }
}
