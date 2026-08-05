<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Events\LedgerImbalanceDetected;
use App\Modules\Ledger\Events\LedgerTransactionPosted;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerIdempotencyKey;
use App\Modules\Ledger\Infrastructure\Models\LedgerJournal;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class LedgerPostingService implements LedgerPostingContract
{
    public function __construct(
        private readonly LedgerAccountResolver $accounts,
        private readonly LedgerAuditLogger $auditLogger,
    ) {}

    public function post(PostLedgerTransaction $command): LedgerTransaction
    {
        $contentHash = $this->computeContentHash($command);

        $existingKey = LedgerIdempotencyKey::query()
            ->where('idempotency_key', $command->idempotencyKey)
            ->first();

        if ($existingKey !== null) {
            return $this->replayOrReject($existingKey, $contentHash, $command->idempotencyKey);
        }

        $this->assertBalanced($command);

        try {
            $transaction = DB::transaction(function () use ($command, $contentHash) {
                $journal = LedgerJournal::query()->where('code', $command->journalCode)->first();

                if ($journal === null) {
                    throw new RuntimeException("Unknown ledger journal code: {$command->journalCode}. Run ledger:seed-catalog.");
                }

                $transaction = LedgerTransaction::query()->create([
                    'journal_id' => $journal->id,
                    'type' => $command->type,
                    'status' => LedgerTransaction::STATUS_POSTED,
                    'source_module' => $command->sourceModule,
                    'business_reference' => $command->businessReference,
                    'currency' => $command->entries[0]->amount->currency,
                    'rule_version' => $command->ruleVersion,
                    'created_by' => $command->createdBy,
                    'metadata' => $command->metadata,
                    'posted_at' => now(),
                    'created_at' => now(),
                ]);

                foreach ($command->entries as $entryInput) {
                    /** @var LedgerAccount $account */
                    $account = $this->accounts->resolve($entryInput->account);

                    LedgerEntry::query()->create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $account->id,
                        'direction' => $entryInput->direction,
                        'amount_minor' => $entryInput->amount->minorUnits,
                        'currency' => $entryInput->amount->currency,
                        'description' => $entryInput->description,
                        'posted_at' => $transaction->posted_at,
                        'created_at' => now(),
                    ]);
                }

                LedgerIdempotencyKey::query()->create([
                    'idempotency_key' => $command->idempotencyKey,
                    'content_hash' => $contentHash,
                    'transaction_id' => $transaction->id,
                    'source_module' => $command->sourceModule,
                ]);

                return $transaction;
            });

            Event::dispatch(new LedgerTransactionPosted($transaction->id));
            $this->auditLogger->record('LedgerTransactionPosted', [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'source_module' => $transaction->source_module,
            ]);

            return $transaction;
        } catch (QueryException $e) {
            if (! $this->isUniqueViolationOn($e, 'ledger_idempotency_keys_idempotency_key_unique')) {
                throw $e;
            }

            // Another worker won the race between our initial SELECT and our
            // INSERT (docs/chantiers/P002-CHANTIER.md invariant:
            // "sérialisation concurrente de deux demandes identiques"). Our
            // own transaction+entries were rolled back with the DB
            // transaction above; recover the winner's transaction instead.
            $winningKey = LedgerIdempotencyKey::query()
                ->where('idempotency_key', $command->idempotencyKey)
                ->firstOrFail();

            return $this->replayOrReject($winningKey, $contentHash, $command->idempotencyKey);
        }
    }

    private function replayOrReject(LedgerIdempotencyKey $existingKey, string $contentHash, string $idempotencyKey): LedgerTransaction
    {
        if ($existingKey->content_hash !== $contentHash) {
            throw new LedgerIdempotencyConflictException($idempotencyKey);
        }

        return $existingKey->transaction()->firstOrFail();
    }

    private function assertBalanced(PostLedgerTransaction $command): void
    {
        if ($command->entries === []) {
            throw new LedgerImbalanceException(['reason' => 'no_entries']);
        }

        $currency = $command->entries[0]->amount->currency;
        $debits = 0;
        $credits = 0;

        foreach ($command->entries as $entry) {
            if ($entry->amount->currency !== $currency) {
                Event::dispatch(new LedgerImbalanceDetected($command->idempotencyKey, ['reason' => 'mixed_currency']));
                $this->auditLogger->record('LedgerImbalanceDetected', ['reason' => 'mixed_currency', 'idempotency_key' => $command->idempotencyKey]);

                throw new LedgerImbalanceException(['reason' => 'mixed_currency']);
            }

            if ($entry->amount->minorUnits <= 0) {
                throw new LedgerImbalanceException(['reason' => 'non_positive_amount']);
            }

            if ($entry->direction === LedgerEntry::DIRECTION_DEBIT) {
                $debits += $entry->amount->minorUnits;
            } else {
                $credits += $entry->amount->minorUnits;
            }
        }

        if ($debits !== $credits) {
            Event::dispatch(new LedgerImbalanceDetected($command->idempotencyKey, ['debits' => $debits, 'credits' => $credits]));
            $this->auditLogger->record('LedgerImbalanceDetected', [
                'reason' => 'debit_credit_mismatch',
                'idempotency_key' => $command->idempotencyKey,
                'debits' => $debits,
                'credits' => $credits,
            ]);

            throw new LedgerImbalanceException(['debits' => $debits, 'credits' => $credits]);
        }
    }

    private function computeContentHash(PostLedgerTransaction $command): string
    {
        $normalized = [
            'type' => $command->type,
            'journal' => $command->journalCode,
            'business_reference' => $command->businessReference,
            'entries' => array_map(
                static fn ($entry) => [
                    'code' => $entry->account->code,
                    'owner_type' => $entry->account->ownerType,
                    'owner_id' => $entry->account->ownerId,
                    'direction' => $entry->direction,
                    'amount_minor' => $entry->amount->minorUnits,
                    'currency' => $entry->amount->currency,
                ],
                $command->entries,
            ),
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function isUniqueViolationOn(QueryException $e, string $constraintName): bool
    {
        return $e->getCode() === '23505' && str_contains((string) $e->getMessage(), $constraintName);
    }
}
