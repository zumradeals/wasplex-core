<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use App\Modules\SmartProfile\Infrastructure\Models\ProfileAnswer;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Support\Carbon;

/**
 * Voluntary, optional and editable member profile answers. Taxonomies carry
 * their own interaction semantics: boolean facts remain independent, a
 * single-choice facet replaces its previous choice, and multi-choice facets
 * can keep several selected options.
 */
final class ProfileAnswerService
{
    public function __construct(private readonly ProfileTaxonomyService $taxonomies) {}

    /**
     * @return array<string, array<int, array{id: string, code: string, label: string, facet: string, input_type: string, answer: ?bool, declared: bool, declared_at: ?string}>>
     */
    public function viewForAccount(string $accountId): array
    {
        $activeTaxonomies = $this->taxonomies->listActive();

        $declaredByTaxonomy = ProfileAnswer::query()
            ->where('account_id', $accountId)
            ->whereNull('withdrawn_at')
            ->get()
            ->keyBy('profile_taxonomy_id');

        $grouped = [];

        foreach ($activeTaxonomies as $taxonomy) {
            $answer = $declaredByTaxonomy->get($taxonomy->id);

            $grouped[$taxonomy->category][] = [
                'id' => $taxonomy->id,
                'code' => $taxonomy->code,
                'label' => $taxonomy->label,
                'facet' => $taxonomy->facet ?: $taxonomy->code,
                'input_type' => $taxonomy->input_type ?: ProfileTaxonomy::INPUT_BOOLEAN,
                'answer' => $answer?->answer_value,
                'declared' => $answer?->answer_value === true,
                'declared_at' => $answer?->declared_at?->toIso8601String(),
            ];
        }

        return $grouped;
    }

    public function answer(string $accountId, string $taxonomyId, bool $value): ProfileAnswer
    {
        $taxonomy = ProfileTaxonomy::query()->find($taxonomyId);

        if ($taxonomy === null) {
            throw new ProfileTaxonomyNotFoundException($taxonomyId);
        }

        if ($taxonomy->status !== ProfileTaxonomy::STATUS_ACTIVE) {
            throw new ProfileTaxonomyNotActiveException;
        }

        $existing = ProfileAnswer::query()
            ->where('account_id', $accountId)
            ->where('profile_taxonomy_id', $taxonomyId)
            ->whereNull('withdrawn_at')
            ->first();

        if ($existing !== null && $existing->answer_value === $value) {
            return $existing;
        }

        if ($existing !== null) {
            $existing->update(['withdrawn_at' => Carbon::now('UTC')]);
        }

        if ($value && $taxonomy->input_type === ProfileTaxonomy::INPUT_SINGLE_CHOICE) {
            $facet = $taxonomy->facet ?: $taxonomy->code;

            ProfileAnswer::query()
                ->where('account_id', $accountId)
                ->whereNull('withdrawn_at')
                ->whereHas('taxonomy', fn ($query) => $query
                    ->where('facet', $facet)
                    ->where('input_type', ProfileTaxonomy::INPUT_SINGLE_CHOICE)
                    ->where('id', '!=', $taxonomyId))
                ->update(['withdrawn_at' => Carbon::now('UTC')]);
        }

        return ProfileAnswer::query()->create([
            'account_id' => $accountId,
            'profile_taxonomy_id' => $taxonomyId,
            'source' => ProfileAnswer::SOURCE_DECLARED_BY_USER,
            'answer_value' => $value,
            'declared_at' => Carbon::now('UTC'),
        ]);
    }

    public function declare(string $accountId, string $taxonomyId): ProfileAnswer
    {
        return $this->answer($accountId, $taxonomyId, true);
    }

    public function withdraw(string $accountId, string $taxonomyId): void
    {
        ProfileAnswer::query()
            ->where('account_id', $accountId)
            ->where('profile_taxonomy_id', $taxonomyId)
            ->whereNull('withdrawn_at')
            ->update(['withdrawn_at' => Carbon::now('UTC')]);
    }
}
