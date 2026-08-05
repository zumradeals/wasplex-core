<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Events\LedgerTransactionPosted;
use App\Modules\Ledger\Events\LedgerTransactionReversed;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerIdempotencyKey;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransactionLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class LedgerCompensationService implements LedgerCompensationContract
{
    public function __construct(private readonly LedgerAuditLogger $auditLogger) {}

    public function proposeReversal(string $originalTransactionId, string $reason, string $proposedBy): LedgerTransaction
    {
        $original = LedgerTransaction::query()->find($originalTransactionId);

        if ($original === null || ! $original->isPosted()) {
            throw new LedgerTransactionNotFoundException($originalTransactionId);
        }

        $idempotencyKey = "ledger.correction.propose:{$originalTransactionId}";
        $contentHash = hash('sha256', json_encode(['original' => $originalTransactionId, 'reason' => $reason], JSON_THROW_ON_ERROR));

        $existingKey = LedgerIdempotencyKey::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existingKey !== null) {
            if ($existingKey->content_hash !== $contentHash) {
                throw new LedgerIdempotencyConflictException($idempotencyKey);
            }

            return $existingKey->transaction()->firstOrFail();
        }

        return DB::transaction(function () use ($original, $reason, $proposedBy, $idempotencyKey, $contentHash) {
            $pending = LedgerTransaction::query()->create([
                'journal_id' => $original->journal_id,
                'type' => LedgerTransaction::TYPE_ADMIN_CORRECTION,
                'status' => LedgerTransaction::STATUS_PENDING_APPROVAL,
                'source_module' => 'ledger.admin',
                'business_reference' => $original->business_reference,
                'currency' => $original->currency,
                'created_by' => $proposedBy,
                'metadata' => ['reason' => $reason, 'original_transaction_id' => $original->id],
                'created_at' => now(),
            ]);

            foreach ($original->entries as $originalEntry) {
                LedgerEntry::query()->create([
                    'transaction_id' => $pending->id,
                    'account_id' => $originalEntry->account_id,
                    'direction' => $originalEntry->direction === LedgerEntry::DIRECTION_DEBIT
                        ? LedgerEntry::DIRECTION_CREDIT
                        : LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $originalEntry->amount_minor,
                    'currency' => $originalEntry->currency,
                    'description' => "Correction de {$original->id} : {$reason}",
                    'created_at' => now(),
                ]);
            }

            LedgerTransactionLink::query()->create([
                'transaction_id' => $pending->id,
                'linked_transaction_id' => $original->id,
                'link_type' => LedgerTransactionLink::LINK_TYPE_REVERSAL,
            ]);

            LedgerIdempotencyKey::query()->create([
                'idempotency_key' => $idempotencyKey,
                'content_hash' => $contentHash,
                'transaction_id' => $pending->id,
                'source_module' => 'ledger.admin',
            ]);

            $this->auditLogger->record('LedgerCorrectionProposed', [
                'transaction_id' => $pending->id,
                'original_transaction_id' => $original->id,
                'proposed_by' => $proposedBy,
            ]);

            return $pending;
        });
    }

    public function approve(string $pendingTransactionId, string $approvedBy): LedgerTransaction
    {
        $pending = LedgerTransaction::query()->find($pendingTransactionId);

        if ($pending === null) {
            throw new LedgerTransactionNotFoundException($pendingTransactionId);
        }

        if ($pending->status !== LedgerTransaction::STATUS_PENDING_APPROVAL) {
            throw new LedgerCorrectionStateException($pendingTransactionId, $pending->status);
        }

        if ($pending->created_by === $approvedBy) {
            throw new LedgerCorrectionSelfApprovalException;
        }

        $posted = DB::transaction(function () use ($pending, $approvedBy) {
            $now = now();

            // Entries must be touched *before* the transaction flips to
            // 'posted' — the immutability trigger on ledger_entries checks
            // its parent transaction's *current* status, which within this
            // same DB transaction would already read back as 'posted' if
            // we updated the transaction row first.
            LedgerEntry::query()->where('transaction_id', $pending->id)->update(['posted_at' => $now]);

            $pending->forceFill([
                'status' => LedgerTransaction::STATUS_POSTED,
                'posted_at' => $now,
                'approved_by' => $approvedBy,
                'approved_at' => $now,
            ])->save();

            return $pending->refresh();
        });

        $originalId = LedgerTransactionLink::query()
            ->where('transaction_id', $posted->id)
            ->value('linked_transaction_id');

        Event::dispatch(new LedgerTransactionPosted($posted->id));
        Event::dispatch(new LedgerTransactionReversed($posted->id, $originalId));

        $this->auditLogger->record('LedgerCorrectionApproved', [
            'transaction_id' => $posted->id,
            'original_transaction_id' => $originalId,
            'approved_by' => $approvedBy,
        ]);

        return $posted;
    }

    public function reject(string $pendingTransactionId, string $rejectedBy, string $reason): LedgerTransaction
    {
        $pending = LedgerTransaction::query()->find($pendingTransactionId);

        if ($pending === null) {
            throw new LedgerTransactionNotFoundException($pendingTransactionId);
        }

        if ($pending->status !== LedgerTransaction::STATUS_PENDING_APPROVAL) {
            throw new LedgerCorrectionStateException($pendingTransactionId, $pending->status);
        }

        $pending->forceFill(['status' => LedgerTransaction::STATUS_REJECTED])->save();

        $this->auditLogger->record('LedgerCorrectionRejected', [
            'transaction_id' => $pending->id,
            'rejected_by' => $rejectedBy,
            'reason' => $reason,
        ]);

        return $pending->refresh();
    }
}
