<?php

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\MembershipStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Domain\Events\OrganizationCreated;
use App\Modules\Identity\Domain\Events\UserSpaceCreated;
use App\Modules\Identity\Domain\Events\UserSpaceSwitched;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SpaceService
{
    public function __construct(private readonly CapabilityService $capabilities) {}

    public function activateAdvertiser(Account $account, string $organizationName): UserSpace
    {
        return DB::transaction(function () use ($account, $organizationName): UserSpace {
            $existing = $account->spaces()->where('kind', SpaceKind::Advertiser->value)->first();

            if ($existing instanceof UserSpace) {
                $this->ensureAdvertiserCapabilities($account, $existing);

                return $existing;
            }

            $organization = Organization::query()->create([
                'created_by_account_id' => $account->id,
                'name' => trim($organizationName),
                'slug' => Str::slug($organizationName).'-'.Str::lower(Str::random(6)),
                'kind' => 'advertiser',
                'status' => 'active',
            ]);

            $organization->memberships()->create([
                'account_id' => $account->id,
                'status' => MembershipStatus::Active,
                'title' => 'Propriétaire',
            ]);

            $space = $account->spaces()->create([
                'organization_id' => $organization->id,
                'kind' => SpaceKind::Advertiser,
                'label' => $organization->name,
                'status' => 'active',
            ]);

            $space->memberships()->create([
                'account_id' => $account->id,
                'status' => MembershipStatus::Active,
                'title' => 'Propriétaire',
            ]);

            $this->ensureAdvertiserCapabilities($account, $space);

            DB::afterCommit(static function () use ($account, $organization, $space): void {
                event(new OrganizationCreated($organization->id, $account->id));
                event(new UserSpaceCreated($account->id, $space->id, SpaceKind::Advertiser->value));
            });

            return $space;
        });
    }

    public function switch(Account $account, AccountSession $session, UserSpace $space): void
    {
        if ($space->account_id !== $account->id || $space->status !== 'active') {
            throw new AuthorizationException('Cet espace ne vous est pas accessible.');
        }

        if ($space->kind === SpaceKind::Administration && $session->mfa_verified_at === null) {
            throw new AuthorizationException('Une authentification multifacteur récente est requise.');
        }

        $session->forceFill(['active_space_id' => $space->id, 'last_seen_at' => now()])->save();
        $space->forceFill(['last_opened_at' => now()])->save();

        event(new UserSpaceSwitched($account->id, $space->id, $session->id));
    }

    private function ensureAdvertiserCapabilities(Account $account, UserSpace $space): void
    {
        foreach ([
            'advertiser.space.view',
            'advertiser.space.manage',
            'advertiser.profile.view',
            'advertiser.profile.manage',
            'advertiser.brand.view',
            'advertiser.brand.manage',
            'advertiser.media.view',
            'advertiser.media.upload',
            'advertiser.media.manage',
            'advertiser.wallet.view',
            'advertiser.wallet.fund',
            'advertiser.campaign.view',
            'advertiser.campaign.create',
            'advertiser.campaign.manage',
            'advertiser.campaign.quote',
            'advertiser.campaign.fund',
            'advertiser.campaign.submit',
            'organization.members.invite',
        ] as $capability) {
            if ($this->capabilities->allows($account, $capability, $space)) {
                continue;
            }

            $this->capabilities->grant(
                account: $account,
                capability: $capability,
                space: $space,
                organizationId: $space->organization_id,
                reason: 'Activation de son espace annonceur',
            );
        }
    }
}
