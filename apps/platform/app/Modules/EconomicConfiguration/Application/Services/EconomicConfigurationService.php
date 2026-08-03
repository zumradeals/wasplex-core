<?php

namespace App\Modules\EconomicConfiguration\Application\Services;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use Carbon\CarbonImmutable;
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
     * Applique une répartition exprimée directement en pourcentages par le fondateur.
     * Les champs techniques de chaque classe sont conservés depuis la version publiée,
     * tandis que la création, l'approbation et la publication restent traçables.
     *
     * @param  array<string, int|float|string>  $percentages
     * @return Collection<int, EconomicClassVersion>
     */
    public function applyPercentageDistribution(array $percentages, string $actorAccountId): Collection
    {
        $classCodes = ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM'];
        $weights = collect($classCodes)->mapWithKeys(function (string $code) use ($percentages): array {
            if (! array_key_exists($code, $percentages)) {
                throw ValidationException::withMessages([
                    "percentages.{$code}" => "Le pourcentage {$code} est obligatoire.",
                ]);
            }

            return [$code => $this->percentageToBasisPoints($percentages[$code])];
        });

        if ($weights->sum() !== 10_000) {
            throw ValidationException::withMessages([
                'percentages' => 'La somme des quatre pourcentages doit être exactement égale à 100 %.',
            ]);
        }

        return DB::transaction(function () use ($actorAccountId, $classCodes, $weights): Collection {
            $classes = EconomicClass::query()
                ->whereIn('code', $classCodes)
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            if ($classes->count() !== count($classCodes)) {
                throw ValidationException::withMessages([
                    'percentages' => 'Les quatre classes économiques canoniques sont obligatoires.',
                ]);
            }

            $active = EconomicClassVersion::query()
                ->whereIn('economic_class_id', $classes->pluck('id'))
                ->where('state', ConfigurationState::Published)
                ->where('effective_from', '<=', now())
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                ->lockForUpdate()
                ->get()
                ->keyBy('economic_class_id');

            if ($active->count() !== count($classCodes)) {
                throw ValidationException::withMessages([
                    'percentages' => 'Une version publiée doit exister pour chacune des quatre classes.',
                ]);
            }

            $approved = collect();

            foreach ($classCodes as $code) {
                $class = $classes->get($code);

                if (! $class instanceof EconomicClass) {
                    throw new LogicException("La classe économique {$code} est introuvable.");
                }

                $current = $active->get($class->id);

                if (! $current instanceof EconomicClassVersion) {
                    throw new LogicException("La version publiée de {$code} est introuvable.");
                }

                $weight = (int) $weights->get($code);

                if ($current->weight_basis_points === $weight) {
                    continue;
                }

                $draft = $this->createDraft($class, [
                    'public_name' => $current->public_name,
                    'quota_monthly' => $current->quota_monthly,
                    'weight_basis_points' => $weight,
                    'targeting_coefficient_basis_points' => $current->targeting_coefficient_basis_points,
                    'features' => $current->features ?? [],
                ], $actorAccountId);

                $approved->push($this->approve($draft, $actorAccountId));
            }

            if ($approved->isEmpty()) {
                return collect();
            }

            return $this->publishMany($approved->pluck('id')->all(), $actorAccountId);
        });
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

            $transitionAt = $this->transitionAfter(...$active->values()->all());

            foreach ($candidates as $candidate) {
                $current = $active->get($candidate->economic_class_id);

                if ($current instanceof EconomicClassVersion && $current->id !== $candidate->id) {
                    $current->forceFill(['effective_to' => $transitionAt])->save();
                }

                $candidate->forceFill([
                    'state' => ConfigurationState::Published,
                    'effective_from' => $transitionAt,
                    'effective_to' => null,
                    'published_at' => $transitionAt,
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
            'effective_to' => $this->transitionAfter($version),
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

    private function percentageToBasisPoints(int|float|string $percentage): int
    {
        $normalized = str_replace(',', '.', trim((string) $percentage));

        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages([
                'percentages' => 'Chaque pourcentage doit être un nombre valide.',
            ]);
        }

        $basisPoints = (int) round(((float) $normalized) * 100);

        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw ValidationException::withMessages([
                'percentages' => 'Chaque pourcentage doit être compris entre 0 % et 100 %.',
            ]);
        }

        return $basisPoints;
    }

    private function transitionAfter(EconomicClassVersion ...$versions): CarbonImmutable
    {
        $transitionAt = CarbonImmutable::now()->startOfSecond()->addSecond();

        foreach ($versions as $version) {
            if ($version->effective_from !== null && ! $transitionAt->greaterThan($version->effective_from)) {
                $transitionAt = $version->effective_from->startOfSecond()->addSecond();
            }
        }

        return $transitionAt;
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
