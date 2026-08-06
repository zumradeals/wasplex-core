<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Services;

use App\Modules\Matching\Infrastructure\Models\MatchingConfiguration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seuils configurables dès ce chantier, non appliqués (docs/chantiers/
 * P008-CHANTIER.md §3.2) : aucun compteur de livraison réelle n'existe
 * avant le Feed (P009). Tant qu'aucune version n'est publiée, l'admin ne
 * voit que les valeurs par défaut du schéma.
 */
final class MatchingConfigurationService
{
    /**
     * @return Collection<int, MatchingConfiguration>
     */
    public function listAll(): Collection
    {
        return MatchingConfiguration::query()->orderByDesc('created_at')->get();
    }

    /**
     * @param  array{frequency_window_hours?: int, frequency_max_per_window?: int, fatigue_threshold?: int}  $data
     */
    public function createDraft(array $data): MatchingConfiguration
    {
        return MatchingConfiguration::query()->create([
            ...$data,
            'status' => MatchingConfiguration::STATUS_DRAFT,
        ]);
    }

    /**
     * @param  array{frequency_window_hours?: int, frequency_max_per_window?: int, fatigue_threshold?: int}  $data
     */
    public function updateDraft(string $configurationId, array $data): MatchingConfiguration
    {
        $configuration = $this->find($configurationId);

        if ($configuration->status !== MatchingConfiguration::STATUS_DRAFT) {
            throw new MatchingConfigurationNotDraftException;
        }

        $configuration->update($data);

        return $configuration;
    }

    public function publish(string $configurationId): MatchingConfiguration
    {
        $configuration = $this->find($configurationId);

        return DB::transaction(function () use ($configuration): MatchingConfiguration {
            MatchingConfiguration::query()
                ->where('status', MatchingConfiguration::STATUS_PUBLISHED)
                ->update(['status' => MatchingConfiguration::STATUS_DRAFT]);

            $configuration->update(['status' => MatchingConfiguration::STATUS_PUBLISHED, 'published_at' => now()]);

            return $configuration->refresh();
        });
    }

    private function find(string $configurationId): MatchingConfiguration
    {
        $configuration = MatchingConfiguration::query()->find($configurationId);

        if ($configuration === null) {
            throw new MatchingConfigurationNotFoundException($configurationId);
        }

        return $configuration;
    }
}
