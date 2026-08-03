<?php

namespace App\Modules\EconomicConfiguration\Application\Services;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class EconomicConfigurationService
{
    public const CACHE_KEY = 'economic_configuration.published.v1';

    /** @return Collection<int, EconomicClassVersion> */
    public function published(): Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), fn (): Collection => EconomicClassVersion::query()
            ->with('economicClass')
            ->where('state', ConfigurationState::Published)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderBy('economic_class_id')
            ->get());
    }

    /** @param array{public_name:string,quota_monthly:int,weight_basis_points:int,targeting_coefficient_basis_points:int,features?:array<string,mixed>} $attributes */
    public function createDraft(EconomicClass $class, array $attributes, ?string $actorAccountId = null): EconomicClassVersion
    {
        return DB::transaction(function () use ($class, $attributes, $actorAccountId): EconomicClassVersion {
            $version = EconomicClassVersion::query()->create([
                'economic_class_id' => $class->id,
                'version' => ((int) $class->versions()->max('version')) + 1,
                'state' => ConfigurationState::Draft,
                'public_name' => trim($attributes['public_name']),
                'quota_monthly' => $attributes['quota_monthly'],
                'weight_basis_points' => $attributes['weight_basis_points'],
                'targeting_coefficient_basis_points' => $attributes['targeting_coefficient_basis_points'],
                'features' => $attributes['features'] ?? [],
                'created_by_account_id' => $actorAccountId,
            ]);

            $this->assertVersionValues($version);

            return $version;
        });
    }

    public function approve(EconomicClassVersion $version, string $actorAccountId): EconomicClassVersion
    {
        if ($version->state !== ConfigurationState::Draft) {
            throw ValidationException::withMessages(['state' => 'Seule une version brouillon peut être approuvée.']);
        }

        $version->forceFill([
            'state' => ConfigurationState::Approved,
            'approved_by_account_id' => $actorAccountId,
            'approved_at' => now(),
        ])->save();

        return $version->refresh();
    }

    public function publish(EconomicClassVersion $version, string $actorAccountId): EconomicClassVersion
    {
        $published = $this->publishMany([$version->id], $actorAccountId)->first();

        if (! $published instanceof EconomicClassVersion) {
            throw new LogicException('La version économique publiée est introuvable.');
        }

        return $published;
    }

    /**
     * Publie une ou plusieurs versions approuvées dans une seule transaction afin que la
     * répartition économique ne traverse jamais un état intermédiaire différent de 100 %.
     *
     * @param  list<string>  $versionIds
     * @return Collection<int, EconomicClassVersion>
     */
    public function publishMany(array $versionIds, string $actorAccountId): Collection
    {
        $versionIds = array_values(array_unique(array_filter($versionIds)));

        if ($versionIds === []) {
            throw ValidationException::withMessages([
                'version_ids' => 'Sélectionnez au moins une version approuvée à publier.',
            ]);
        }

        return DB::transaction(function () use ($versionIds, $actorAccountId): Collection {
            $candidates = EconomicClassVersion::query()
                ->whereIn('id', $versionIds)
                ->lockForUpdate()
                ->get();

            if ($candidates->count() !== count($versionIds)) {
                throw ValidationException::withMessages([
                    'version_ids' => 'Une version sélectionnée est introuvable.',
                ]);
            }

            if ($candidates->contains(
                fn (EconomicClassVersion $version): bool => $version->state !== ConfigurationState::Approved,
            )) {
                throw ValidationException::withMessages([
                    'version_ids' => 'Toutes les versions sélectionnées doivent être approuvées.',
                ]);
            }

            if ($candidates->pluck('economic_class_id')->unique()->count() !== $candidates->count()) {
                throw ValidationException::withMessages([
                    'version_ids' => 'Une seule version peut être publiée par classe dans une même décision.',
                ]);
            }

            $active = EconomicClassVersion::query()
                ->where('state', ConfigurationState::Published)
                ->where('effective_from', '<=', now())
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                ->lockForUpdate()
                ->get()
                ->keyBy('economic_class_id');

            $projected = $active->collect();

            foreach ($candidates as $candidate) {
                $projected->put($candidate->economic_class_id, $candidate);
            }

            if ($projected->count() !== 4 || $projected->sum('weight_basis_points') !== 10_000) {
                throw ValidationException::withMessages([
                    'version_ids' => 'La décision publiée doit couvrir quatre classes et totaliser exactement 100 %.',
                ]);
            }

            $publishedAt = now();

            foreach ($candidates as $candidate) {
                $current = $active->get($candidate->economic_class_id);

                if ($current instanceof EconomicClassVersion && $current->id !== $candidate->id) {
                    $current->forceFill(['effective_to' => $publishedAt])->save();
                }

                $candidate->forceFill([
                    'state' => ConfigurationState::Published,
                    'effective_from' => $publishedAt,
                    'effective_to' => null,
                    'published_at' => $publishedAt,
                    'published_by_account_id' => $actorAccountId,
                ])->save();
            }

            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));

            return $candidates
                ->map(fn (EconomicClassVersion $version): EconomicClassVersion => $version->refresh())
                ->values();
        });
    }

    public function suspend(EconomicClassVersion $version, string $actorAccountId, string $reason): void
    {
        if ($version->state !== ConfigurationState::Published) {
            throw ValidationException::withMessages(['state' => 'Seule une version publiée peut être suspendue.']);
        }

        $version->forceFill([
            'state' => ConfigurationState::Suspended,
            'effective_to' => now(),
            'suspended_by_account_id' => $actorAccountId,
            'suspension_reason' => trim($reason),
        ])->save();

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<array-key, int|string>  $weights
     * @return array{total_basis_points:int,valid:bool}
     */
    public function simulateWeights(array $weights): array
    {
        $total = array_sum(array_map('intval', $weights));

        return ['total_basis_points' => $total, 'valid' => $total === 10_000];
    }

    private function assertVersionValues(EconomicClassVersion $version): void
    {
        if ($version->quota_monthly < 0) {
            throw ValidationException::withMessages(['quota_monthly' => 'Le quota ne peut pas être négatif.']);
        }

        if ($version->weight_basis_points < 0 || $version->weight_basis_points > 10_000) {
            throw ValidationException::withMessages(['weight_basis_points' => 'Le poids doit être compris entre 0 et 10 000 points de base.']);
        }

        if ($version->targeting_coefficient_basis_points < 1) {
            throw ValidationException::withMessages(['targeting_coefficient_basis_points' => 'Le coefficient doit être strictement positif.']);
        }
    }
}
