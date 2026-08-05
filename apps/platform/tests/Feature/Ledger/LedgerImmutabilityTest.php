<?php

declare(strict_types=1);

use App\Modules\Ledger\Application\Services\LedgerCompensationContract;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Shared\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

function postSampleTransaction(string $idempotencyKey): LedgerTransaction
{
    return app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: $idempotencyKey,
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(100, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(100, 'WP')),
        ],
    ));
}

test('PostgreSQL refuses to update a posted transaction', function (): void {
    $transaction = postSampleTransaction('immutable-update-1');

    expect(fn () => DB::table('ledger_transactions')
        ->where('id', $transaction->id)
        ->update(['business_reference' => 'hacked']))
        ->toThrow(QueryException::class);
});

test('PostgreSQL refuses to delete a posted transaction', function (): void {
    $transaction = postSampleTransaction('immutable-delete-1');

    expect(fn () => DB::table('ledger_transactions')->where('id', $transaction->id)->delete())
        ->toThrow(QueryException::class);
});

test('PostgreSQL refuses to modify the entries of a posted transaction', function (): void {
    $transaction = postSampleTransaction('immutable-entries-1');
    $entryId = $transaction->entries()->first()->id;

    expect(fn () => DB::table('ledger_entries')->where('id', $entryId)->update(['amount_minor' => 1]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('ledger_entries')->where('id', $entryId)->delete())
        ->toThrow(QueryException::class);
});

test('PostgreSQL refuses to modify an idempotency key once inserted', function (): void {
    postSampleTransaction('immutable-key-1');

    expect(fn () => DB::table('ledger_idempotency_keys')
        ->where('idempotency_key', 'immutable-key-1')
        ->update(['content_hash' => str_repeat('0', 64)]))
        ->toThrow(QueryException::class);
});

test('a pending_approval transaction remains mutable until it is posted', function (): void {
    $original = postSampleTransaction('pending-mutable-original');

    $pending = app(LedgerCompensationContract::class)
        ->proposeReversal($original->id, 'test correction', 'proposer-account');

    // No exception: pending rows are not yet locked by the trigger.
    DB::table('ledger_transactions')->where('id', $pending->id)->update(['business_reference' => 'still-editable']);

    expect(DB::table('ledger_transactions')->where('id', $pending->id)->value('business_reference'))
        ->toBe('still-editable');
});
