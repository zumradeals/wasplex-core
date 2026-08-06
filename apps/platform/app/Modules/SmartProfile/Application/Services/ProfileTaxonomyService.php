<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Database\Eloquent\Collection;

/**
 * Catalogue plat, sans versionnage JSON (docs/chantiers/P008-CHANTIER.md
 * §5) : chaque taxonomie est éditable individuellement pendant `draft`
 * puis seulement activable/suspendable — le pattern le plus simple pour un
 * administrateur non technicien (exigence explicite du fondateur).
 */
final class ProfileTaxonomyService
{
    /**
     * @return Collection<int, ProfileTaxonomy>
     */
    public function listAll(): Collection
    {
        return ProfileTaxonomy::query()->orderBy('category')->orderBy('label')->get();
    }

    /**
     * @return Collection<int, ProfileTaxonomy>
     */
    public function listActive(): Collection
    {
        return ProfileTaxonomy::query()
            ->where('status', ProfileTaxonomy::STATUS_ACTIVE)
            ->orderBy('category')
            ->orderBy('label')
            ->get();
    }

    /**
     * @param  array{code: string, category: string, label: string, freshness_days?: int|null}  $data
     */
    public function create(array $data): ProfileTaxonomy
    {
        if (! in_array($data['category'], ProfileTaxonomy::CATEGORIES, true)) {
            throw new InvalidProfileTaxonomyCategoryException($data['category']);
        }

        return ProfileTaxonomy::query()->create([
            'code' => $data['code'],
            'category' => $data['category'],
            'label' => $data['label'],
            'freshness_days' => $data['freshness_days'] ?? null,
            'status' => ProfileTaxonomy::STATUS_DRAFT,
        ]);
    }

    /**
     * Category is immutable after creation — it carries the taxonomy's
     * meaning (docs/04 §9 : distinctions obligatoires). Only the label and
     * freshness may be corrected.
     *
     * @param  array{label?: string, freshness_days?: int|null}  $data
     */
    public function update(string $taxonomyId, array $data): ProfileTaxonomy
    {
        $taxonomy = $this->find($taxonomyId);
        $taxonomy->fill(array_intersect_key($data, array_flip(['label', 'freshness_days'])));
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
