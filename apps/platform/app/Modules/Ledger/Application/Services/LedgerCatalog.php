<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Enums\LedgerAccountStatus;
use App\Modules\Ledger\Domain\Enums\LedgerJournalStatus;
use App\Modules\Ledger\Domain\Exceptions\InvalidLedgerPosting;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccountType;
use App\Modules\Ledger\Infrastructure\Models\LedgerJournal;
use Illuminate\Support\Facades\DB;

final class LedgerCatalog
{
    public function createAccountType(
        string $code,
        string $name,
        EntryDirection $normalBalance,
        ?string $description = null,
    ): LedgerAccountType {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($code, $name, $normalBalance, $description): LedgerAccountType {
            $type = LedgerAccountType::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => trim($name),
                    'normal_balance' => $normalBalance,
                    'description' => $description,
                    'created_at' => now(),
                ],
            );

            if ($type->normal_balance !== $normalBalance || $type->name !== trim($name)) {
                throw new InvalidLedgerPosting("Le type de compte {$code} existe avec une autre définition.");
            }

            return $type;
        });
    }

    public function createJournal(string $code, string $name): LedgerJournal
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($code, $name): LedgerJournal {
            $journal = LedgerJournal::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => trim($name),
                    'status' => LedgerJournalStatus::Active,
                    'created_at' => now(),
                ],
            );

            if ($journal->name !== trim($name)) {
                throw new InvalidLedgerPosting("Le journal {$code} existe avec une autre définition.");
            }

            return $journal;
        });
    }

    /** @param array<string, mixed> $metadata */
    public function openAccount(
        string $typeCode,
        string $code,
        string $unit,
        string $currency,
        ?string $ownerType = null,
        ?string $ownerId = null,
        ?string $countryCode = null,
        array $metadata = [],
    ): LedgerAccount {
        $type = LedgerAccountType::query()->where('code', strtoupper(trim($typeCode)))->first();

        if ($type === null) {
            throw new InvalidLedgerPosting("Le type de compte {$typeCode} n’existe pas.");
        }

        $attributes = [
            'account_type_id' => $type->id,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'unit' => strtoupper(trim($unit)),
            'currency' => strtoupper(trim($currency)),
            'country_code' => $countryCode === null ? null : strtoupper(trim($countryCode)),
        ];

        return DB::transaction(function () use ($code, $attributes, $metadata): LedgerAccount {
            $account = LedgerAccount::query()->firstOrCreate(
                ['code' => trim($code)],
                [...$attributes, 'status' => LedgerAccountStatus::Active, 'metadata' => $metadata ?: null],
            );

            foreach ($attributes as $field => $value) {
                if ($account->getAttribute($field) !== $value) {
                    throw new InvalidLedgerPosting("Le compte {$code} existe avec une autre définition.");
                }
            }

            return $account;
        });
    }
}
