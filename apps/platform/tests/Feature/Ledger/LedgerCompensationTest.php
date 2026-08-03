<?php

use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Enums\LedgerLinkType;
use App\Modules\Ledger\Domain\Exceptions\InvalidLedgerPosting;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransactionLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('corrects a posted transaction only with a linked compensating transaction', function (): void {
    $catalog = app(LedgerCatalog::class);
    $catalog->createAccountType('ASSET', 'Actif', EntryDirection::Debit);
    $catalog->createAccountType('LIABILITY', 'Passif', EntryDirection::Credit);
    $catalog->createJournal('GENERAL', 'Journal général');
    $cash = $catalog->openAccount('ASSET', 'wasplex.cash.clearing', 'WP', 'XOF');
    $user = $catalog->openAccount('LIABILITY', 'user.available.wp', 'WP', 'XOF');

    $ledger = app(LedgerContract::class);
    $original = $ledger->post(new PostingRequest(
        journalCode: 'GENERAL',
        type: 'TEST_POSTING',
        sourceModule: 'tests',
        businessReference: 'test:compensation:001',
        idempotencyKey: 'test-original-001',
        unit: 'WP',
        currency: 'XOF',
        entries: [
            new PostingEntry($cash->id, EntryDirection::Debit, 900, 'Débit initial'),
            new PostingEntry($user->id, EntryDirection::Credit, 900, 'Crédit initial'),
        ],
        justification: 'Transaction de test à compenser',
    ));

    $reversal = $ledger->reverse(
        transactionId: $original->transactionId,
        idempotencyKey: 'test-reversal-001',
        justification: 'Correction validée pour le test P002',
        traceId: 'test-trace-reversal',
    );

    $reversalTransaction = LedgerTransaction::query()->with('entries')->findOrFail($reversal->transactionId);
    $link = LedgerTransactionLink::query()->firstOrFail();

    expect($reversalTransaction->entries->firstWhere('account_id', $cash->id)?->direction)
        ->toBe(EntryDirection::Credit)
        ->and($reversalTransaction->entries->firstWhere('account_id', $user->id)?->direction)
        ->toBe(EntryDirection::Debit)
        ->and($link->source_transaction_id)->toBe($original->transactionId)
        ->and($link->target_transaction_id)->toBe($reversal->transactionId)
        ->and($link->link_type)->toBe(LedgerLinkType::Reversal)
        ->and(LedgerTransaction::query()->count())->toBe(2);

    $replayed = $ledger->reverse(
        transactionId: $original->transactionId,
        idempotencyKey: 'test-reversal-001',
        justification: 'Correction validée pour le test P002',
        traceId: 'test-trace-reversal',
    );

    expect($replayed->transactionId)->toBe($reversal->transactionId)
        ->and($replayed->replayed)->toBeTrue()
        ->and(LedgerTransaction::query()->count())->toBe(2);

    expect(fn () => $ledger->reverse(
        transactionId: $original->transactionId,
        idempotencyKey: 'test-reversal-002',
        justification: 'Une seconde correction ne doit pas passer',
    ))->toThrow(InvalidLedgerPosting::class);
});
