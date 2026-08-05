<?php

declare(strict_types=1);

use App\Modules\Ledger\Application\Services\LedgerCompensationContract;
use App\Modules\Ledger\Application\Services\LedgerCorrectionSelfApprovalException;
use App\Modules\Ledger\Application\Services\LedgerCorrectionStateException;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Application\Services\LedgerTransactionNotFoundException;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransactionLink;
use App\Shared\Money\Money;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();

    $this->original = app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'correction-original-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(175, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(175, 'WP')),
        ],
    ));
});

test('proposing a reversal creates a pending_approval transaction with flipped entries', function (): void {
    $pending = app(LedgerCompensationContract::class)->proposeReversal($this->original->id, 'erreur de saisie', 'proposer-1');

    expect($pending->status)->toBe(LedgerTransaction::STATUS_PENDING_APPROVAL);
    expect($pending->posted_at)->toBeNull();

    $originalDirections = $this->original->entries()->pluck('direction', 'account_id');
    $pendingDirections = $pending->entries()->pluck('direction', 'account_id');

    foreach ($originalDirections as $accountId => $direction) {
        $flipped = $direction === LedgerEntry::DIRECTION_DEBIT ? LedgerEntry::DIRECTION_CREDIT : LedgerEntry::DIRECTION_DEBIT;
        expect($pendingDirections[$accountId])->toBe($flipped);
    }

    expect(LedgerTransactionLink::query()
        ->where('transaction_id', $pending->id)
        ->where('linked_transaction_id', $this->original->id)
        ->exists())->toBeTrue();
});

test('approving a correction by a different account posts it and links it to the original', function (): void {
    $compensation = app(LedgerCompensationContract::class);
    $pending = $compensation->proposeReversal($this->original->id, 'erreur de saisie', 'proposer-1');

    $posted = $compensation->approve($pending->id, 'approver-1');

    expect($posted->status)->toBe(LedgerTransaction::STATUS_POSTED);
    expect($posted->approved_by)->toBe('approver-1');
    expect($posted->posted_at)->not->toBeNull();
    expect($posted->entries()->whereNull('posted_at')->count())->toBe(0);
});

test('the proposer cannot approve their own correction', function (): void {
    $compensation = app(LedgerCompensationContract::class);
    $pending = $compensation->proposeReversal($this->original->id, 'erreur de saisie', 'same-account');

    expect(fn () => $compensation->approve($pending->id, 'same-account'))
        ->toThrow(LedgerCorrectionSelfApprovalException::class);

    expect($pending->refresh()->status)->toBe(LedgerTransaction::STATUS_PENDING_APPROVAL);
});

test('rejecting a correction leaves it rejected and never posted', function (): void {
    $compensation = app(LedgerCompensationContract::class);
    $pending = $compensation->proposeReversal($this->original->id, 'erreur de saisie', 'proposer-1');

    $rejected = $compensation->reject($pending->id, 'approver-1', 'montant correct finalement');

    expect($rejected->status)->toBe(LedgerTransaction::STATUS_REJECTED);
    expect($rejected->posted_at)->toBeNull();
});

test('approving an already-decided correction is refused', function (): void {
    $compensation = app(LedgerCompensationContract::class);
    $pending = $compensation->proposeReversal($this->original->id, 'erreur de saisie', 'proposer-1');
    $compensation->approve($pending->id, 'approver-1');

    expect(fn () => $compensation->approve($pending->id, 'approver-2'))
        ->toThrow(LedgerCorrectionStateException::class);
});

test('proposing a reversal of an unknown transaction is refused', function (): void {
    expect(fn () => app(LedgerCompensationContract::class)->proposeReversal('unknown-id', 'reason', 'proposer-1'))
        ->toThrow(LedgerTransactionNotFoundException::class);
});
