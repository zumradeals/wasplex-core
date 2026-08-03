<?php

namespace App\Modules\EconomicConfiguration\Application\Services;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        if ($version->state !== ConfigurationState::Approved) {
            throw ValidationException::withMessages(['state' => 'La version doit être approuvée avant publication.']);
        }

        return DB::transaction(function () use ($version, $actorAccountId): EconomicClassVersion {
            $siblings = EconomicClassVersion::query()
                ->where('economic_class_id', $version->economic_class_id)
                ->where('state', ConfigurationState::Published)
                ->lockForUpdate()
                ->get();

            foreach ($siblings as $published) {
                $published->forceFill(['effective_to' => now()])->save();
            }

            $version->forceFill([
                'state' => ConfigurationState::Published,
                'effective_from' => now(),
                'published_at' => now(),
                'published_by_account_id' => $actorAccountId,
            ])->save();

            $this->assertPublishedWeightsAfter($version);
            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));

            return $version->refresh();
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

    /** @return array{total_basis_points:int,valid:bool} */
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

    private function assertPublishedWeightsAfter(EconomicClassVersion $candidate): void
    {
        $active = EconomicClassVersion::query()
            ->where('state', ConfigurationState::Published)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->get();

        if ($active->count() === 4 && $active->sum('weight_basis_points') !== 10_000) {
            throw ValidationException::withMessages(['weight_basis_points' => 'Les quatre classes publiées doivent totaliser exactement 100 %.']);
        }
    }
}
