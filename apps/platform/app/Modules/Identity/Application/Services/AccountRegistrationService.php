<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Docs/09-compte-universel-et-mon-espace-intelligent-wasplex.md #7:
 * one universal account, one personal space, created together.
 */
final class AccountRegistrationService
{
    public function register(
        string $identifierType,
        string $identifierValue,
        string $password,
        string $countryCode,
        string $language = 'fr',
    ): Account {
        return DB::transaction(function () use ($identifierType, $identifierValue, $password, $countryCode, $language) {
            $account = Account::create([
                'status' => 'active',
                'password' => Hash::make($password),
                'country_code' => $countryCode,
                'language' => $language,
            ]);

            AccountIdentifier::create([
                'account_id' => $account->id,
                'type' => $identifierType,
                'value' => $identifierValue,
                'is_primary' => true,
            ]);

            PersonalProfile::create(['account_id' => $account->id]);

            $space = UserSpace::create([
                'space_type' => UserSpace::TYPE_USER,
                'owner_account_id' => $account->id,
                'status' => 'active',
            ]);

            SpaceMembership::create([
                'user_space_id' => $space->id,
                'account_id' => $account->id,
                'status' => 'active',
                'is_default' => true,
                'joined_at' => now(),
            ]);

            return $account->refresh();
        });
    }
}
