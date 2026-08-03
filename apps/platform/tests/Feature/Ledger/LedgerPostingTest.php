<?php

use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Exceptions\IdempotencyConflict;
use App\Modules\Ledger\Domain\Exceptions\InvalidLedgerPosting;
use App\Modules\Ledger\Domain\Exceptions\UnbalancedTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerIdempotencyKey;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('posts one balanced immutable transaction', function (): void {
    [$cashAccount, $liabilityAccount] = createPostingLedgerCatalog();
    $result = app(LedgerContract::class)->post(ledgerRequest(
        debitAccountId: $cashAccount->id,
        creditAccountId: $liabilityAccount->id,
    ));

    $transaction = LedgerTransaction::query()->with('entries')->findOrFail($result->transactionId);

    expect($result->replayed)->toBeFalse()
        ->and($transaction->total_minor)->toBe(500)
        ->and($transaction->currency)->toBe('XOF')
        ->and($transaction->unit)->toBe('WP')
        ->and($transaction->entries)->toHaveCount(2)
        ->and($transaction->entries->where('direction', EntryDirection::Debit)->sum('amount_minor'))->toBe(500)
        ->and($transaction->entries->where('direction', EntryDirection::Credit)->sum('amount_minor'))->toBe(500);

    expect(fn () => $transaction->forceFill(['justification' => 'Altération'])->save())
        ->toThrow(LogicException::class);
    expect(fn () => $transaction->delete())->toThrow(LogicException::class);
});

it('replays the same idempotent request without duplicating value', function (): void {
    [$cashAccount, $liabilityAccount] = createPostingLedgerCatalog();
    $request = ledgerRequest(
        debitAccountId: $cashAccount->id,
        creditAccountId: $liabilityAccount->id,
    );

    $firstWorker = app(LedgerContract::class);
    $secondWorker = app(LedgerContract::class);
    $first = $firstWorker->post($request);
    $second = $secondWorker->post($request);

    expect($second->transactionId)->toBe($first->transactionId)
        ->and($second->replayed)->toBeTrue()
        ->and(LedgerTransaction::query()->count())->toBe(1)
        ->and(LedgerEntry::query()->count())->toBe(2)
        ->and(LedgerIdempotencyKey::query()->count())->toBe(1);
});

it('refuses an idempotency key reused for different economic content', function (): void {
    [$cashAccount, $liabilityAccount] = createPostingLedgerCatalog();
    $ledger = app(LedgerContract::class);
    $ledger->post(ledgerRequest(
        debitAccountId: $cashAccount->id,
        creditAccountId: $liabilityAccount->id,
    ));

    expect(fn () => $ledger->post(ledgerRequest(
        debitAccountId: $cashAccount->id,
        creditAccountId: $liabilityAccount->id,
        amount: 700,
    )))->toThrow(IdempotencyConflict::class);

    expect(LedgerTransaction::query()->count())->toBe(1)
        ->and(LedgerEntry::query()->count())->toBe(2);
});

it('rolls back every ledger record when a posting cannot complete', function (): void {
    [$cashAccount] = createPostingLedgerCatalog();
    $request = ledgerRequest(
        debitAccountId: $cashAccount->id,
        creditAccountId: '01H00000000000000000000000',
    );

    expect(fn () => app(LedgerContract::class)->post($request))
        ->toThrow(InvalidLedgerPosting::class);

    expect(LedgerTransaction::query()->count())->toBe(0)
        ->and(LedgerEntry::query()->count())->toBe(0)
        ->and(LedgerIdempotencyKey::query()->count())->toBe(0);
});

it('refuses imbalance and account currency mismatch before commit', function (): void {
    [$cashAccount, $liabilityAccount] = createPostingLedgerCatalog();
    $unbalanced = new PostingRequest(
        journalCode: 'GENERAL',
        type: 'TEST_POSTING',
        sourceModule: 'tests',
        businessReference: 'test:imbalance',
        idempotencyKey: 'ledger-test-imbalance',
        unit: 'WP',
        currency: 'XOF',
        entries: [
            new PostingEntry($cashAccount->id, EntryDirection::Debit, 500, 'Débit'),
            new PostingEntry($liabilityAccount->id, EntryDirection::Credit, 499, 'Crédit'),
        ],
    );

    expect(fn () => app(LedgerContract::class)->post($unbalanced))
        ->toThrow(UnbalancedTransaction::class);

    $usdLiability = app(LedgerCatalog::class)->openAccount(
        typeCode: 'LIABILITY',
        code: 'user.available.usd',
        unit: 'USD',
        currency: 'USD',
    );

    expect(fn () => app(LedgerContract::class)->post(ledgerRequest(
        debitAccountId: $cashAccount->id,
        creditAccountId: $usdLiability->id,
        idempotencyKey: 'ledger-test-currency',
    )))->toThrow(InvalidLedgerPosting::class);

    expect(LedgerTransaction::query()->count())->toBe(0);
});

/** @return array{0: LedgerAccount, 1: LedgerAccount} */
function createPostingLedgerCatalog(): array
{
    $catalog = app(LedgerCatalog::class);
    $catalog->createAccountType('ASSET', 'Actif', EntryDirection::Debit);
    $catalog->createAccountType('LIABILITY', 'Passif', EntryDirection::Credit);
    $catalog->createJournal('GENERAL', 'Journal général');

    $cashAccount = $catalog->openAccount(
        typeCode: 'ASSET',
        code: 'wasplex.cash.clearing',
        unit: 'WP',
        currency: 'XOF',
    );
    $liabilityAccount = $catalog->openAccount(
        typeCode: 'LIABILITY',
        code: 'user.available.wp',
        unit: 'WP',
        currency: 'XOF',
    );

    return [$cashAccount, $liabilityAccount];
}

function ledgerRequest(
    string $debitAccountId,
    string $creditAccountId,
    int $amount = 500,
    string $idempotencyKey = 'ledger-test-posting',
): PostingRequest {
    return new PostingRequest(
        journalCode: 'GENERAL',
        type: 'TEST_POSTING',
        sourceModule: 'tests',
        businessReference: 'test:posting:001',
        idempotencyKey: $idempotencyKey,
        unit: 'WP',
        currency: 'XOF',
        entries: [
            new PostingEntry($debitAccountId, EntryDirection::Debit, $amount, 'Débit de test'),
            new PostingEntry($creditAccountId, EntryDirection::Credit, $amount, 'Crédit de test'),
        ],
        justification: 'Preuve des invariants P002',
        ruleVersion: 'p002-v1',
        metadata: ['proof_reference' => 'test-proof-001'],
        traceId: 'test-trace-ledger',
    );
}
