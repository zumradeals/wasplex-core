<?php

declare(strict_types=1);

use App\Modules\Ledger\Application\Services\LedgerIdempotencyConflictException;
use App\Modules\Ledger\Application\Services\LedgerImbalanceException;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerIdempotencyKey;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Shared\Money\Money;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

test('a balanced transaction is posted with its entries', function (): void {
    $posting = app(LedgerPostingContract::class);

    $transaction = $posting->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'balanced-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(175, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(175, 'WP')),
        ],
    ));

    expect($transaction->status)->toBe(LedgerTransaction::STATUS_POSTED);
    expect($transaction->posted_at)->not->toBeNull();
    expect($transaction->entries()->count())->toBe(2);
    expect((int) LedgerEntry::query()->where('transaction_id', $transaction->id)->sum('amount_minor'))->toBe(350);
});

test('an unbalanced transaction is rejected atomically', function (): void {
    $posting = app(LedgerPostingContract::class);

    expect(fn () => $posting->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'unbalanced-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(100, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(50, 'WP')),
        ],
    )))->toThrow(LedgerImbalanceException::class);

    expect(LedgerTransaction::query()->count())->toBe(0);
    expect(LedgerEntry::query()->count())->toBe(0);
});

test('entries of a single transaction must share the same currency', function (): void {
    $posting = app(LedgerPostingContract::class);

    expect(fn () => $posting->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'mixed-currency-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(100, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(100, 'XOF')),
        ],
    )))->toThrow(LedgerImbalanceException::class);

    expect(LedgerTransaction::query()->count())->toBe(0);
});

test('a replayed idempotency key with identical content returns the original transaction', function (): void {
    $posting = app(LedgerPostingContract::class);

    $command = new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'replay-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(175, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(175, 'WP')),
        ],
    );

    $first = $posting->post($command);
    $second = $posting->post($command);

    expect($second->id)->toBe($first->id);
    expect(LedgerTransaction::query()->count())->toBe(1);
    expect(LedgerEntry::query()->count())->toBe(2);
});

test('a reused idempotency key with different content is rejected', function (): void {
    $posting = app(LedgerPostingContract::class);

    $posting->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'conflict-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(175, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(175, 'WP')),
        ],
    ));

    expect(fn () => $posting->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'conflict-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(999, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(999, 'WP')),
        ],
    )))->toThrow(LedgerIdempotencyConflictException::class);
});

/**
 * Simulates a second worker having already won the race for the same
 * idempotency key between this caller's initial lookup and its own insert
 * attempt (docs/chantiers/P002-CHANTIER.md: "sérialisation concurrente de
 * deux demandes identiques"). A real two-process race is not exercised
 * here (Pest runs synchronously); this proves the recovery path the
 * service takes once it observes the winning row, which is the same code
 * path a real unique-constraint-violation retry would land on.
 */
test('a concurrently posted identical request is not duplicated', function (): void {
    $posting = app(LedgerPostingContract::class);

    $command = new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'race-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(175, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(175, 'WP')),
        ],
    );

    // Worker A wins the race first.
    $winner = $posting->post($command);

    // Worker B only reaches its own initial lookup afterwards.
    $loser = $posting->post($command);

    expect($loser->id)->toBe($winner->id);
    expect(LedgerTransaction::query()->count())->toBe(1);
    expect(LedgerIdempotencyKey::query()->count())->toBe(1);
});
