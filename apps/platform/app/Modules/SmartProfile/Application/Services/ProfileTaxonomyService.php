<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Database\Eloquent\Collection;

final class ProfileTaxonomyService
{
    /**
     * @return Collection<int, ProfileTaxonomy>
     */
    public function listAll(): Collection
    {
        return ProfileTaxonomy::query()->orderBy('category')->orderBy('facet')->orderBy('label')->get();
    }

    /**
     * @return Collection<int, ProfileTaxonomy>
     */
    public function listActive(): Collection
    {
        return ProfileTaxonomy::query()
            ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
            ->orderBy('category')
            ->orderBy('facet')
            ->orderBy('label')
            ->get();
    }

    /**
     * @param  array{code: string, category: string, label: string, facet?: string|null, input_type?: string, freshness_days?: int|null}  $data
     */
    public function create(array $data): ProfileTaxonomy
    {
        if (! in_array($data['category'], ProfileTaxonomy::CATEGORIES, true)) {
            throw new InvalidProfileTaxonomyCategoryException($data['category']);
        }

        $inputType = $data['input_type'] ?? ProfileTaxonomy::INPUT_BOOLEAN;
        $facet = trim((string) ($data['facet'] ?? '')) ?: $data['code'];

        return ProfileTaxonomy::query()->create([
            'code' => $data['code'],
            'category' => $data['category'],
            'facet' => $facet,
            'input_type' => $inputType,
            'label' => $data['label'],
            'freshness_days' => $data['freshness_days'] ?? null,
            'status' => ProfileTaxonomy::STATUS_DRAFT,
        ]);
    }

    /**
     * @param  array{label?: string, facet?: string|null, input_type?: string, freshness_days?: int|null}  $data
     */
    public function update(string $taxonomyId, array $data): ProfileTaxonomy
    {
        $taxonomy = $this->find($taxonomyId);
        $taxonomy->fill(array_intersect_key($data, array_flip(['label', 'facet', 'input_type', 'freshness_days'])));

        if (array_key_exists('facet', $data) && trim((string) $taxonomy->facet) === '') {
            $taxonomy->facet = $taxonomy->code;
        }

        $taxonomy->save();

        return $taxonomy;
    }

    public function activate(string $taxonomyId): ProfileTaxonomy
    {
        $taxonomy = $this->find($taxonomyId);
        $taxonomy->update(['status' => ProfileTaxonomy::STATUS_ACTIVE]);

        return $taxonomy;
    }

    public function suspend(string $taxonomyId): ProfileTaxonomy
    {
        $taxonomy = $this->find($taxonomyId);
        $taxonomy->update(['status' => ProfileTaxonomy::STATUS_SUSPENDED]);

        return $taxonomy;
    }

    private function find(string $taxonomyId): ProfileTaxonomy
    {
        $taxonomy = ProfileTaxonomy::query()->find($taxonomyId);

        if ($taxonomy === null) {
            throw new ProfileTaxonomyNotFoundException($taxonomyId);
        }

        return $taxonomy;
    }
}
