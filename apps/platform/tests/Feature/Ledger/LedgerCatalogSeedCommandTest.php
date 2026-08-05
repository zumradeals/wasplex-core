<?php

declare(strict_types=1);

use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccountType;
use App\Modules\Ledger\Infrastructure\Models\LedgerJournal;

test('the catalog seed command creates the journal, account types and system accounts', function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();

    expect(LedgerJournal::query()->where('code', LedgerJournal::CODE_MAIN)->exists())->toBeTrue();
    expect(LedgerAccountType::query()->count())->toBe(10);
    expect(LedgerAccount::query()->where('owner_type', LedgerAccount::OWNER_TYPE_SYSTEM)->count())->toBe(3);
});

test('running the catalog seed command twice does not create duplicates', function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
    $this->artisan('ledger:seed-catalog')->assertSuccessful();

    expect(LedgerJournal::query()->count())->toBe(1);
    expect(LedgerAccountType::query()->count())->toBe(10);
    expect(LedgerAccount::query()->count())->toBe(3);
});
