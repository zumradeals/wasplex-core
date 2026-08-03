<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Enums\WalletKind;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Domain\Events\WalletCreated;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Illuminate\Support\Facades\DB;

final readonly class WalletCatalog
{
    public function __construct(private LedgerCatalog $ledger) {}

    public function ensureCore(): void
    {
        $this->ledger->createAccountType('ASSET', 'Actif', EntryDirection::Debit, 'Ressources contrôlées par Wasplex.');
        $this->ledger->createAccountType('LIABILITY', 'Passif', EntryDirection::Credit, 'Valeurs dues aux utilisateurs et partenaires.');
        $this->ledger->createJournal('WALLET', 'Wallets et réservations');
        $this->ledger->openAccount(
            typeCode: 'ASSET',
            code: 'wasplex.clearing.geniuspay.sandbox.xof',
            unit: 'WP',
            currency: 'XOF',
            ownerType: 'payment_provider',
            ownerId: 'geniuspay:sandbox',
            countryCode: 'CI',
            metadata: ['provider' => 'geniuspay', 'environment' => 'sandbox'],
        );
    }

    public function forSpace(UserSpace $space): Wallet
    {
        if (! in_array($space->kind, [SpaceKind::User, SpaceKind::Advertiser], true)) {
            throw new WalletException('Cet espace ne possède pas de Wallet.');
        }

        $this->ensureCore();

        return DB::transaction(function () use ($space): Wallet {
            $wallet = Wallet::query()->where('space_id', $space->id)->lockForUpdate()->first();

            if ($wallet === null) {
                $wallet = Wallet::query()->create([
                    'account_id' => $space->account_id,
                    'space_id' => $space->id,
                    'organization_id' => $space->organization_id,
                    'kind' => $space->kind === SpaceKind::Advertiser ? WalletKind::Advertiser : WalletKind::User,
                    'unit' => 'WP',
                    'currency' => 'XOF',
                    'status' => WalletStatus::Active,
                ]);
            }

            if ($wallet->available_ledger_account_id === null) {
                $prefix = "wallet.{$wallet->id}";
                $available = $this->openWalletAccount($wallet, "{$prefix}.available", 'available');
                $reserved = $this->openWalletAccount($wallet, "{$prefix}.reserved", 'reserved');
                $pending = $this->openWalletAccount($wallet, "{$prefix}.pending", 'pending');
                $wallet->forceFill([
                    'available_ledger_account_id' => $available->id,
                    'reserved_ledger_account_id' => $reserved->id,
                    'pending_ledger_account_id' => $pending->id,
                ])->save();

                $wallet->projection()->create([
                    'available_minor' => 0,
                    'reserved_minor' => 0,
                    'pending_minor' => 0,
                    'version' => 0,
                ]);

                DB::afterCommit(static fn () => event(new WalletCreated($wallet->id, $space->id, $wallet->kind->value)));
            }

            return $wallet->load('projection');
        });
    }

    public function clearingAccountId(): string
    {
        $this->ensureCore();

        return $this->ledger->openAccount(
            typeCode: 'ASSET', code: 'wasplex.clearing.geniuspay.sandbox.xof', unit: 'WP', currency: 'XOF',
            ownerType: 'payment_provider', ownerId: 'geniuspay:sandbox', countryCode: 'CI',
            metadata: ['provider' => 'geniuspay', 'environment' => 'sandbox'],
        )->id;
    }

    private function openWalletAccount(Wallet $wallet, string $code, string $compartment): LedgerAccount
    {
        return $this->ledger->openAccount(
            typeCode: 'LIABILITY',
            code: $code,
            unit: $wallet->unit,
            currency: $wallet->currency,
            ownerType: 'wallet',
            ownerId: $wallet->id,
            countryCode: 'CI',
            metadata: ['compartment' => $compartment],
        );
    }
}
