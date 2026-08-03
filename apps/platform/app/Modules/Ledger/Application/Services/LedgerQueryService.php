<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Application\Contracts\LedgerQueryContract;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransactionLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class LedgerQueryService implements LedgerQueryContract
{
    public function transaction(string $transactionId): ?array
    {
        $transaction = LedgerTransaction::query()
            ->with(['journal', 'entries.account', 'outgoingLinks', 'incomingLinks'])
            ->where('status', 'posted')
            ->find($transactionId);

        if ($transaction === null) {
            return null;
        }

        return [
            ...$this->transactionSummary($transaction),
            'justification' => $transaction->justification,
            'rule_version' => $transaction->rule_version,
            'metadata' => $transaction->metadata,
            'trace_id' => $transaction->trace_id,
            'entries' => $transaction->entries->map(static fn (LedgerEntry $entry): array => [
                'id' => $entry->id,
                'account_id' => $entry->account_id,
                'account_code' => $entry->account->code,
                'direction' => $entry->direction->value,
                'amount_minor' => $entry->amount_minor,
                'unit' => $entry->unit,
                'currency' => $entry->currency,
                'description' => $entry->description,
                'external_reference' => $entry->external_reference,
                'posted_at' => $entry->posted_at->toIso8601String(),
            ])->values()->all(),
            'links' => $transaction->outgoingLinks
                ->concat($transaction->incomingLinks)
                ->map(static fn (LedgerTransactionLink $link): array => [
                    'source_transaction_id' => $link->source_transaction_id,
                    'target_transaction_id' => $link->target_transaction_id,
                    'type' => $link->link_type->value,
                ])->values()->all(),
        ];
    }

    public function transactions(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return LedgerTransaction::query()
            ->with('journal')
            ->where('status', 'posted')
            ->when($filters['source_module'] ?? null, static fn ($query, string $value) => $query->where('source_module', $value))
            ->when($filters['type'] ?? null, static fn ($query, string $value) => $query->where('type', $value))
            ->when($filters['currency'] ?? null, static fn ($query, string $value) => $query->where('currency', strtoupper($value)))
            ->when($filters['business_reference'] ?? null, static fn ($query, string $value) => $query->where('business_reference', 'like', "%{$value}%"))
            ->orderByDesc('posted_at')
            ->paginate(min(max($perPage, 1), 100))
            ->through(fn (LedgerTransaction $transaction): array => $this->transactionSummary($transaction));
    }

    public function accounts(int $perPage = 25): LengthAwarePaginator
    {
        return LedgerAccount::query()
            ->with('accountType')
            ->orderBy('code')
            ->paginate(min(max($perPage, 1), 100))
            ->through(fn (LedgerAccount $account): array => $this->accountSummary($account));
    }

    /** @return array<string, mixed> */
    private function transactionSummary(LedgerTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'journal' => $transaction->journal->code,
            'type' => $transaction->type,
            'status' => $transaction->status->value,
            'source_module' => $transaction->source_module,
            'business_reference' => $transaction->business_reference,
            'unit' => $transaction->unit,
            'currency' => $transaction->currency,
            'total_minor' => $transaction->total_minor,
            'created_by_account_id' => $transaction->created_by_account_id,
            'beneficiary_account_id' => $transaction->beneficiary_account_id,
            'posted_at' => $transaction->posted_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function accountSummary(LedgerAccount $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'type' => $account->accountType->code,
            'owner_type' => $account->owner_type,
            'owner_id' => $account->owner_id,
            'unit' => $account->unit,
            'currency' => $account->currency,
            'status' => $account->status->value,
            'country_code' => $account->country_code,
            'created_at' => $account->created_at?->toIso8601String(),
        ];
    }
}
