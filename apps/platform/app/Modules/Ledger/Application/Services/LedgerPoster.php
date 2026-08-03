<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostedTransaction;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Enums\LedgerAccountStatus;
use App\Modules\Ledger\Domain\Enums\LedgerJournalStatus;
use App\Modules\Ledger\Domain\Enums\LedgerLinkType;
use App\Modules\Ledger\Domain\Enums\LedgerTransactionStatus;
use App\Modules\Ledger\Domain\Events\LedgerImbalanceDetected;
use App\Modules\Ledger\Domain\Events\LedgerTransactionPosted;
use App\Modules\Ledger\Domain\Events\LedgerTransactionReversed;
use App\Modules\Ledger\Domain\Exceptions\IdempotencyConflict;
use App\Modules\Ledger\Domain\Exceptions\InvalidLedgerPosting;
use App\Modules\Ledger\Domain\Exceptions\UnbalancedTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerIdempotencyKey;
use App\Modules\Ledger\Infrastructure\Models\LedgerJournal;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransactionLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class LedgerPoster implements LedgerContract
{
    public function __construct(private CanonicalPayloadHasher $hasher) {}

    public function post(PostingRequest $request): PostedTransaction
    {
        [$debitTotal, $creditTotal] = $this->totals($request);

        if ($debitTotal !== $creditTotal) {
            $this->reportImbalance($request, $debitTotal, $creditTotal);

            throw new UnbalancedTransaction($debitTotal, $creditTotal);
        }

        $payloadHash = $this->hasher->hash($request);

        return DB::transaction(function () use ($request, $payloadHash, $debitTotal): PostedTransaction {
            LedgerIdempotencyKey::query()->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'source_module' => $request->sourceModule,
                'idempotency_key' => $request->idempotencyKey,
                'payload_hash' => $payloadHash,
                'transaction_id' => null,
                'created_at' => now(),
            ]);

            $idempotency = LedgerIdempotencyKey::query()
                ->where('source_module', $request->sourceModule)
                ->where('idempotency_key', $request->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($idempotency === null) {
                throw new InvalidLedgerPosting('Le verrou d’idempotence n’a pas pu être établi.');
            }

            if (! hash_equals($idempotency->payload_hash, $payloadHash)) {
                throw IdempotencyConflict::forKey($request->sourceModule, $request->idempotencyKey);
            }

            if ($idempotency->transaction_id !== null) {
                return new PostedTransaction($idempotency->transaction_id, true);
            }

            $journal = LedgerJournal::query()
                ->where('code', strtoupper($request->journalCode))
                ->lockForUpdate()
                ->first();

            if ($journal === null || $journal->status !== LedgerJournalStatus::Active) {
                throw new InvalidLedgerPosting('Le journal comptable demandé est absent ou fermé.');
            }

            $accounts = $this->lockedAccounts($request);
            $postedAt = $request->postedAt ?? now()->toImmutable();
            $createdAt = now();

            $transaction = LedgerTransaction::query()->create([
                'journal_id' => $journal->id,
                'type' => $request->type,
                'status' => LedgerTransactionStatus::Draft,
                'source_module' => $request->sourceModule,
                'business_reference' => $request->businessReference,
                'idempotency_key' => $request->idempotencyKey,
                'rule_version' => $request->ruleVersion,
                'unit' => strtoupper($request->unit),
                'currency' => strtoupper($request->currency),
                'total_minor' => $debitTotal,
                'created_by_account_id' => $request->actorAccountId,
                'beneficiary_account_id' => $request->beneficiaryAccountId,
                'justification' => $request->justification,
                'metadata' => $request->metadata === [] ? null : $request->metadata,
                'trace_id' => $request->traceId,
                'posted_at' => $postedAt,
                'created_at' => $createdAt,
            ]);

            foreach ($request->entries as $entry) {
                $account = $accounts[$entry->accountId];

                LedgerEntry::query()->create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $account->id,
                    'direction' => $entry->direction,
                    'amount_minor' => $entry->amountMinor,
                    'unit' => strtoupper($request->unit),
                    'currency' => strtoupper($request->currency),
                    'description' => $entry->description,
                    'external_reference' => $entry->externalReference,
                    'posted_at' => $postedAt,
                    'created_at' => $createdAt,
                ]);
            }

            $transaction->forceFill(['status' => LedgerTransactionStatus::Posted])->save();

            $idempotency->forceFill(['transaction_id' => $transaction->id])->save();

            DB::afterCommit(static fn () => event(new LedgerTransactionPosted(
                transactionId: $transaction->id,
                sourceModule: $transaction->source_module,
                businessReference: $transaction->business_reference,
                totalMinor: $transaction->total_minor,
                unit: $transaction->unit,
                currency: $transaction->currency,
            )));

            return new PostedTransaction($transaction->id);
        }, 5);
    }

    public function reverse(
        string $transactionId,
        string $idempotencyKey,
        string $justification,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): PostedTransaction {
        if (trim($justification) === '') {
            throw new InvalidLedgerPosting('Une compensation exige une justification.');
        }

        return DB::transaction(function () use (
            $transactionId,
            $idempotencyKey,
            $justification,
            $actorAccountId,
            $traceId,
        ): PostedTransaction {
            $original = LedgerTransaction::query()
                ->with('entries')
                ->where('status', LedgerTransactionStatus::Posted->value)
                ->lockForUpdate()
                ->find($transactionId);

            if ($original === null) {
                throw new InvalidLedgerPosting('La transaction à compenser n’existe pas.');
            }

            $existingLink = LedgerTransactionLink::query()
                ->where('source_transaction_id', $original->id)
                ->where('link_type', LedgerLinkType::Reversal)
                ->first();

            if ($existingLink !== null) {
                $existingReversal = LedgerTransaction::query()->findOrFail($existingLink->target_transaction_id);

                if ($existingReversal->idempotency_key !== $idempotencyKey) {
                    throw new InvalidLedgerPosting('Cette transaction possède déjà une compensation.');
                }
            }

            $request = new PostingRequest(
                journalCode: $original->journal->code,
                type: 'REVERSAL',
                sourceModule: 'ledger',
                businessReference: "reversal:{$original->id}",
                idempotencyKey: $idempotencyKey,
                unit: $original->unit,
                currency: $original->currency,
                entries: $original->entries->map(static fn (LedgerEntry $entry): PostingEntry => new PostingEntry(
                    accountId: $entry->account_id,
                    direction: $entry->direction->opposite(),
                    amountMinor: $entry->amount_minor,
                    description: Str::limit("Compensation — {$entry->description}", 500, ''),
                    externalReference: $entry->external_reference,
                ))->values()->all(),
                actorAccountId: $actorAccountId,
                beneficiaryAccountId: $original->beneficiary_account_id,
                justification: $justification,
                ruleVersion: $original->rule_version,
                metadata: ['reverses_transaction_id' => $original->id],
                traceId: $traceId,
            );

            $result = $this->post($request);

            if ($existingLink !== null) {
                if ($existingLink->target_transaction_id !== $result->transactionId) {
                    throw new InvalidLedgerPosting('La clé de compensation ne correspond pas au lien existant.');
                }

                return $result;
            }

            LedgerTransactionLink::query()->create([
                'source_transaction_id' => $original->id,
                'target_transaction_id' => $result->transactionId,
                'link_type' => LedgerLinkType::Reversal,
                'created_at' => now(),
            ]);

            DB::afterCommit(static fn () => event(new LedgerTransactionReversed(
                originalTransactionId: $original->id,
                reversalTransactionId: $result->transactionId,
            )));

            return $result;
        }, 5);
    }

    /** @return array{0: int, 1: int} */
    private function totals(PostingRequest $request): array
    {
        $debits = 0;
        $credits = 0;

        foreach ($request->entries as $entry) {
            if ($entry->direction === EntryDirection::Debit) {
                if ($entry->amountMinor > PHP_INT_MAX - $debits) {
                    throw new InvalidLedgerPosting('Le total des débits dépasse la capacité entière du système.');
                }

                $debits += $entry->amountMinor;
            } else {
                if ($entry->amountMinor > PHP_INT_MAX - $credits) {
                    throw new InvalidLedgerPosting('Le total des crédits dépasse la capacité entière du système.');
                }

                $credits += $entry->amountMinor;
            }
        }

        return [$debits, $credits];
    }

    /** @return array<string, LedgerAccount> */
    private function lockedAccounts(PostingRequest $request): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (PostingEntry $entry): string => $entry->accountId,
            $request->entries,
        )));

        $accounts = LedgerAccount::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== count($ids)) {
            throw new InvalidLedgerPosting('Au moins un compte comptable est introuvable.');
        }

        $result = [];

        foreach ($ids as $id) {
            $account = $accounts->get($id);

            if (! $account instanceof LedgerAccount || $account->status !== LedgerAccountStatus::Active) {
                throw new InvalidLedgerPosting('Au moins un compte comptable est inactif.');
            }

            if ($account->unit !== strtoupper($request->unit) || $account->currency !== strtoupper($request->currency)) {
                throw new InvalidLedgerPosting("Le compte {$account->code} n’accepte pas l’unité ou la devise demandée.");
            }

            $result[$id] = $account;
        }

        return $result;
    }

    private function reportImbalance(PostingRequest $request, int $debits, int $credits): void
    {
        Log::critical('ledger.imbalance_detected', [
            'source_module' => $request->sourceModule,
            'business_reference' => $request->businessReference,
            'unit' => $request->unit,
            'currency' => $request->currency,
            'debit_total' => $debits,
            'credit_total' => $credits,
            'trace_id' => $request->traceId,
        ]);

        event(new LedgerImbalanceDetected(
            sourceModule: $request->sourceModule,
            businessReference: $request->businessReference,
            unit: strtoupper($request->unit),
            currency: strtoupper($request->currency),
            debitTotal: $debits,
            creditTotal: $credits,
            traceId: $request->traceId,
        ));
    }
}
