<?php

use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use App\Modules\Wallet\Application\Data\ReservationRequest;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Domain\Enums\ReservationStatus;
use App\Modules\Wallet\Domain\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reserves, releases and captures value without allowing a negative available balance', function (): void {
    registerAccount($this, 'reservation@example.com');
    $space = UserSpace::query()->where('kind', 'user')->firstOrFail();
    $wallet = app(WalletCatalog::class)->forSpace($space);
    creditWalletForReservationTest($wallet, 1000);

    $service = app(WalletReservationContract::class);
    $first = $service->reserve(new ReservationRequest(
        walletId: $wallet->id,
        purpose: 'campaign_budget',
        amountMinor: 700,
        businessReference: 'campaign:test:one',
        idempotencyKey: 'reservation-test-one',
    ));

    expect($first->status)->toBe(ReservationStatus::Active)
        ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(300)
        ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(700);

    expect(fn () => $service->reserve(new ReservationRequest(
        walletId: $wallet->id,
        purpose: 'campaign_budget',
        amountMinor: 400,
        businessReference: 'campaign:test:too-much',
        idempotencyKey: 'reservation-test-too-much',
    )))->toThrow(InsufficientWalletBalance::class);

    $service->release($first->id, 'release-test-one', 'Campagne annulée');
    expect($wallet->projection()->firstOrFail()->available_minor)->toBe(1000)
        ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(0);

    $second = $service->reserve(new ReservationRequest(
        walletId: $wallet->id,
        purpose: 'campaign_budget',
        amountMinor: 500,
        businessReference: 'campaign:test:two',
        idempotencyKey: 'reservation-test-two',
    ));
    $destination = app(LedgerCatalog::class)->openAccount(
        typeCode: 'REVENUE', code: 'wasplex.revenue.test', unit: 'WP', currency: 'XOF',
    );
    $captured = $service->capture($second->id, $destination->id, 'capture-test-two');
    $replayed = $service->capture($second->id, $destination->id, 'capture-test-two-retry');

    expect($captured->status)->toBe(ReservationStatus::Captured)
        ->and($replayed->id)->toBe($captured->id)
        ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(500)
        ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(0)
        ->and(LedgerTransaction::query()->count())->toBe(5);
});

it('reconstructs a corrupted projection from immutable ledger entries', function (): void {
    registerAccount($this, 'rebuild@example.com');
    $space = UserSpace::query()->where('kind', 'user')->firstOrFail();
    $wallet = app(WalletCatalog::class)->forSpace($space);
    creditWalletForReservationTest($wallet, 2500);

    $wallet->projection()->update(['available_minor' => 999999]);
    app(WalletProjector::class)->rebuild($wallet);

    expect($wallet->projection()->firstOrFail()->available_minor)->toBe(2500);
});

it('rejects reuse of a reservation key for a different economic intent', function (): void {
    registerAccount($this, 'reservation-idempotency@example.com');
    $space = UserSpace::query()->where('kind', 'user')->firstOrFail();
    $wallet = app(WalletCatalog::class)->forSpace($space);
    creditWalletForReservationTest($wallet, 2000);
    $service = app(WalletReservationContract::class);

    $service->reserve(new ReservationRequest(
        walletId: $wallet->id,
        purpose: 'campaign_budget',
        amountMinor: 700,
        businessReference: 'campaign:test:idempotency',
        idempotencyKey: 'reservation-same-key',
    ));

    expect(fn () => $service->reserve(new ReservationRequest(
        walletId: $wallet->id,
        purpose: 'campaign_budget',
        amountMinor: 800,
        businessReference: 'campaign:test:idempotency-other',
        idempotencyKey: 'reservation-same-key',
    )))->toThrow(WalletException::class, 'autre réservation');

    expect(WalletReservation::query()->count())->toBe(1)
        ->and(LedgerTransaction::query()->where('type', 'VALUE_RESERVATION')->count())->toBe(1);
});

function creditWalletForReservationTest(Wallet $wallet, int $amount): void
{
    app('Illuminate\Contracts\Console\Kernel')->call('ledger:bootstrap-core');
    $clearing = app(WalletCatalog::class)->clearingAccountId();
    $posted = app(LedgerContract::class)->post(new PostingRequest(
        journalCode: 'WALLET', type: 'TEST_WALLET_CREDIT', sourceModule: 'wallet-tests',
        businessReference: "wallet-test-credit:{$wallet->id}", idempotencyKey: "wallet-test-credit:{$wallet->id}:{$amount}",
        unit: 'WP', currency: 'XOF', entries: [
            new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test'),
            new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet de test'),
        ],
    ));
    app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
}
