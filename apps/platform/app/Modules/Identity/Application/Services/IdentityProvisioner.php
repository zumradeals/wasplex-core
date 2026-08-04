<?php

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\IdentifierType;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Domain\Events\AccountCreated;
use App\Modules\Identity\Domain\Events\UserSpaceCreated;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdentityProvisioner
{
    public function createAccount(string $displayName, string $email, string $password): Account
    {
        return DB::transaction(function () use ($displayName, $email, $password): Account {
            $account = Account::query()->create([
                'password_hash' => $password,
                'status' => 'active',
                'locale' => 'fr',
                'timezone' => 'UTC',
            ]);

            $account->identifiers()->create([
                'type' => IdentifierType::Email,
                'normalized_value' => Str::lower(trim($email)),
                'display_value' => trim($email),
                'is_primary' => true,
            ]);

            $account->profile()->create(['display_name' => trim($displayName)]);

            $space = $account->spaces()->create([
                'kind' => SpaceKind::User,
                'label' => 'Mon espace',
                'status' => 'active',
            ]);

            $space->memberships()->create([
                'account_id' => $account->id,
                'status' => 'active',
                'title' => 'Propriétaire',
            ]);

            foreach ([
                'account.self.manage',
                'account.sessions.manage',
                'advertiser.space.activate',
                'wallet.view.self',
                'wallet.deposit.create.self',
                'wallet.history.view.self',
                'advertising.profile.view.self',
                'advertising.profile.manage.self',
                'advertising.consent.view.self',
                'advertising.consent.manage.self',
                'advertising.explanation.view.self',
            ] as $capability) {
                $account->capabilityGrants()->create([
                    'space_id' => $space->id,
                    'capability' => $capability,
                    'valid_from' => now(),
                    'reason' => 'Provisionnement du compte universel',
                ]);
            }

            DB::afterCommit(static function () use ($account, $space): void {
                event(new AccountCreated($account->id));
                event(new UserSpaceCreated($account->id, $space->id, SpaceKind::User->value));
            });

            return $account->load(['identifiers', 'profile', 'spaces']);
        });
    }
}
