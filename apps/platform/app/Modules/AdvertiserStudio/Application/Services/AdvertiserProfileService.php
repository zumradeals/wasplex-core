<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\AdvertiserProfile;

/**
 * docs/13-studio-annonceur-wasplex.md §8: activating the advertiser space
 * never creates a second account — the profile here is Studio-owned data
 * layered on top of Identity's Organization (P001), auto-created empty on
 * first access rather than at organization-creation time, since Identity
 * must not depend on AdvertiserStudio (docs/CLAUDE.md §6).
 */
final class AdvertiserProfileService
{
    public function getOrCreate(string $organizationId): AdvertiserProfile
    {
        return AdvertiserProfile::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            ['status' => AdvertiserProfile::STATUS_DRAFT],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $organizationId, array $data): AdvertiserProfile
    {
        $profile = $this->getOrCreate($organizationId);
        $profile->update($data);

        return $profile->refresh();
    }

    public function verify(AdvertiserProfile $profile): AdvertiserProfile
    {
        $profile->update(['status' => AdvertiserProfile::STATUS_VERIFIED, 'verified_at' => now()]);

        return $profile->refresh();
    }

    public function restrict(AdvertiserProfile $profile): AdvertiserProfile
    {
        $profile->update(['status' => AdvertiserProfile::STATUS_RESTRICTED]);

        return $profile->refresh();
    }

    public function restore(AdvertiserProfile $profile): AdvertiserProfile
    {
        $profile->update(['status' => $profile->verified_at !== null ? AdvertiserProfile::STATUS_VERIFIED : AdvertiserProfile::STATUS_DRAFT]);

        return $profile->refresh();
    }
}
