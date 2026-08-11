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
        $codes = array_values(array_unique(array_filter($taxonomyCodes, 'is_string')));
        if ($codes === []) {
            return true;
        }

        $taxonomies = ProfileTaxonomy::query()
            ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
            ->whereIn('category', self::TARGETABLE_CATEGORIES)
            ->whereIn('code', $codes)
            ->get(['id', 'code', 'category']);

        if ($taxonomies->count() !== count($codes)) {
            return false;
        }

        $selectedByFacet = $taxonomies
            ->groupBy(fn (ProfileTaxonomy $taxonomy) => $this->facetFor($taxonomy))
            ->map(fn ($items) => $items->pluck('code')->all());

        $matchedCodes = ProfileAnswer::query()
            ->where('account_id', $accountId)
            ->where('answer_value', true)
            ->whereNull('withdrawn_at')
            ->whereHas('taxonomy', fn ($query) => $query
                ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
                ->whereIn('category', self::TARGETABLE_CATEGORIES)
                ->whereIn('code', $codes))
            ->with('taxonomy:id,code')
            ->get()
            ->pluck('taxonomy.code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // OR is reserved for alternatives of the same real-world facet,
        // e.g. woman OR man, Orange OR MTN as primary network, or one of
        // several approximate areas. Independent facts such as owning a
        // smartphone, a two-wheeler and a car are separate facets, so when
        // an advertiser deliberately selects several of them they are ANDed.
        foreach ($selectedByFacet as $facetCodes) {
            if (array_intersect($facetCodes, $matchedCodes) === []) {
                return false;
            }
        }

        return true;
    }

    private function facetFor(ProfileTaxonomy $taxonomy): string
    {
        $code = $taxonomy->code;

        if (str_starts_with($code, 'demographic.gender.')) {
            return 'demographic.gender';
        }

        if (str_starts_with($code, 'usage.reseau_')) {
            return 'usage.primary_network';
        }

        if (str_starts_with($code, 'territory.')) {
            return 'territory.approximate_area';
        }

        return $code;
    }
}
