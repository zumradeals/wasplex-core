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
                'facet' => $taxonomy->facet ?: $taxonomy->code,
                'input_type' => $taxonomy->input_type ?: ProfileTaxonomy::INPUT_BOOLEAN,
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
            ->get(['id', 'code', 'category', 'facet', 'input_type']);

        if ($taxonomies->count() !== count($codes)) {
            return false;
        }

        $selectedByFacet = $taxonomies
            ->groupBy(fn (ProfileTaxonomy $taxonomy) => $taxonomy->facet ?: $taxonomy->code)
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

        // OR applies between options of the same declared facet. Different
        // facets remain ANDed. This lets an advertiser target Woman OR Man,
        // while Smartphone AND Car remain two independent requirements.
        foreach ($selectedByFacet as $facetCodes) {
            if (array_intersect($facetCodes, $matchedCodes) === []) {
                return false;
            }
        }

        return true;
    }
}
