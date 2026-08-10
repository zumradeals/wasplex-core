<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileAnswer;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;

final class ProfileTargetingService implements ProfileTargetingContract
{
    private const TARGETABLE_CATEGORIES = [
        ProfileTaxonomy::CATEGORY_DEMOGRAPHIC,
        ProfileTaxonomy::CATEGORY_POSSESSION,
        ProfileTaxonomy::CATEGORY_USAGE,
        ProfileTaxonomy::CATEGORY_INTEREST,
        ProfileTaxonomy::CATEGORY_PROJECT,
        ProfileTaxonomy::CATEGORY_SITUATION,
        ProfileTaxonomy::CATEGORY_TERRITORY,
    ];

    public function targetableCatalog(): array
    {
        return ProfileTaxonomy::query()
            ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
            ->whereIn('category', self::TARGETABLE_CATEGORIES)
            ->orderBy('category')->orderBy('label')->get()
            ->groupBy('category')
            ->map(fn ($items) => $items->map(fn (ProfileTaxonomy $taxonomy) => [
                'code' => $taxonomy->code,
                'label' => $taxonomy->label,
            ])->values()->all())
            ->all();
    }

    public function validateTargetableCodes(array $taxonomyCodes): array
    {
        $codes = array_values(array_unique(array_filter($taxonomyCodes, 'is_string')));
        $valid = ProfileTaxonomy::query()
            ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
            ->whereIn('category', self::TARGETABLE_CATEGORIES)
            ->whereIn('code', $codes)->pluck('code')->all();

        return array_values(array_diff($codes, $valid));
    }

    public function accountMatchesAll(string $accountId, array $taxonomyCodes): bool
    {
        $codes = array_values(array_unique($taxonomyCodes));
        if ($codes === []) {
            return true;
        }

        $matched = ProfileAnswer::query()
            ->where('account_id', $accountId)
            ->where('answer_value', true)
            ->whereNull('withdrawn_at')
            ->whereHas('taxonomy', fn ($query) => $query
                ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
                ->whereIn('code', $codes))
            ->distinct('profile_taxonomy_id')
            ->count('profile_taxonomy_id');

        return $matched === count($codes);
    }
}
