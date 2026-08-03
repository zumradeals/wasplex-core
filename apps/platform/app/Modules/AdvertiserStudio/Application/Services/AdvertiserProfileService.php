<?php

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\AdvertiserProfile;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AdvertiserProfileService
{
    public function ensure(UserSpace $space, Account $actor): AdvertiserProfile
    {
        $this->guardSpace($space);

        return AdvertiserProfile::query()->firstOrCreate(
            ['advertiser_space_id' => $space->id],
            [
                'organization_id' => $space->organization_id,
                'created_by_account_id' => $actor->id,
                'display_name' => $space->organization?->name ?? $space->label,
                'country_code' => 'CI',
                'status' => 'active',
            ],
        );
    }

    /** @param array<string, mixed> $data */
    public function update(UserSpace $space, Account $actor, array $data): AdvertiserProfile
    {
        return DB::transaction(function () use ($space, $actor, $data): AdvertiserProfile {
            $profile = $this->ensure($space, $actor);
            $profile->fill([
                'display_name' => trim((string) $data['display_name']),
                'legal_name' => $this->nullableString($data['legal_name'] ?? null),
                'industry' => $this->nullableString($data['industry'] ?? null),
                'website_url' => $this->nullableString($data['website_url'] ?? null),
                'country_code' => strtoupper((string) ($data['country_code'] ?? 'CI')),
                'city' => $this->nullableString($data['city'] ?? null),
                'phone' => $this->nullableString($data['phone'] ?? null),
                'description' => $this->nullableString($data['description'] ?? null),
                'updated_by_account_id' => $actor->id,
            ])->save();

            $space->forceFill(['label' => $profile->display_name])->save();

            if ($space->organization !== null && $space->organization->name !== $profile->display_name) {
                $space->organization->forceFill(['name' => $profile->display_name])->save();
            }

            return $profile->refresh();
        });
    }

    public function completion(AdvertiserProfile $profile): int
    {
        $fields = [
            $profile->display_name,
            $profile->legal_name,
            $profile->industry,
            $profile->city,
            $profile->phone,
            $profile->description,
        ];
        $completed = count(array_filter($fields, static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        return (int) round(($completed / count($fields)) * 100);
    }

    private function guardSpace(UserSpace $space): void
    {
        if ($space->kind !== SpaceKind::Advertiser || $space->organization_id === null) {
            throw new LogicException('Un profil annonceur exige un espace annonceur rattaché à une organisation.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
