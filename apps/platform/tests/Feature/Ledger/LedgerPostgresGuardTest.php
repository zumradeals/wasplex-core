<?php

use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('lets PostgreSQL reject direct mutation and late entries on a posted transaction', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Garde PostgreSQL vérifiée dans le passage CI PostgreSQL.');
    }

    $catalog = app(LedgerCatalog::class);
    $catalog->createAccountType('ASSET', 'Actif', EntryDirection::Debit);
    $catalog->createAccountType('LIABILITY', 'Passif', EntryDirection::Credit);
    $catalog->createJournal('GENERAL', 'Journal général');
    $cash = $catalog->openAccount('ASSET', 'wasplex.cash.clearing', 'WP', 'XOF');
    $user = $catalog->openAccount('LIABILITY', 'user.available.wp', 'WP', 'XOF');
    $posted = app(LedgerContract::class)->post(new PostingRequest(
        journalCode: 'GENERAL',
        type: 'POSTGRES_GUARD_TEST',
        sourceModule: 'tests',
        businessReference: 'test:postgres-guard:001',
        idempotencyKey: 'test-postgres-guard-001',
        unit: 'WP',
        currency: 'XOF',
        entries: [
            new PostingEntry($cash->id, EntryDirection::Debit, 300, 'Débit'),
            new PostingEntry($user->id, EntryDirection::Credit, 300, 'Crédit'),
        ],
    ));
    $transaction = LedgerTransaction::query()->findOrFail($posted->transactionId);

    expect(fn () => DB::transaction(
        fn (): int => DB::table('ledger_transactions')
            ->where('id', $transaction->id)
            ->update(['status' => 'draft']),
    ))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => LedgerEntry::query()->create([
        'transaction_id' => $transaction->id,
        'account_id' => $cash->id,
        'direction' => EntryDirection::Debit,
        'amount_minor' => 1,
        'unit' => 'WP',
        'currency' => 'XOF',
        'description' => 'Écriture tardive interdite',
        'posted_at' => now(),
        'created_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('rolls the P002 migration back cleanly on PostgreSQL', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Rollback PostgreSQL vérifié dans le passage CI PostgreSQL.');
    }

    expect(Schema::hasTable('ledger_transactions'))->toBeTrue()
        ->and(Schema::hasTable('advertising_consents'))->toBeTrue()
        ->and(Schema::hasTable('advertising_matches'))->toBeTrue();

    // P003 à P007, GeniusPay et les trois migrations P008 dépendent du Ledger ou de l’identité.
    $this->artisan('migrate:rollback', ['--step' => 12, '--force' => true])
        ->assertSuccessful();

    expect(Schema::hasTable('ledger_transactions'))->toBeFalse()
        ->and(Schema::hasTable('ledger_entries'))->toBeFalse()
        ->and(Schema::hasTable('ledger_idempotency_keys'))->toBeFalse()
        ->and(Schema::hasTable('advertising_consents'))->toBeFalse()
        ->and(Schema::hasTable('advertising_matches'))->toBeFalse();
});
