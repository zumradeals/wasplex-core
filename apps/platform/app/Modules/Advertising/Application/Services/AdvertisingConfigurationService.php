<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingAdministrationEvent;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingConfigurationVersion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatchAudit;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingPurpose;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegmentEstimate;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AdvertisingConfigurationService
{
    /**
     * @return array{
     *     version:int|null,
     *     minimumSegmentSize:int,
     *     estimateRoundingStep:int,
     *     frequencyWindowHours:int,
     *     frequencyLimit:int,
     *     fatigueLimit:int,
     *     effectiveFrom:string|null
     * }
     */
    public function runtime(): array
    {
        $version = $this->publishedVersion();

        if (! $version instanceof AdvertisingConfigurationVersion) {
            return [
                'version' => null,
                'minimumSegmentSize' => max(1, (int) config('advertising.minimum_segment_size', 25)),
                'estimateRoundingStep' => max(1, (int) config('advertising.estimate_rounding_step', 10)),
                'frequencyWindowHours' => max(1, (int) config('advertising.frequency_window_hours', 24)),
                'frequencyLimit' => max(1, (int) config('advertising.frequency_limit', 3)),
                'fatigueLimit' => max(1, (int) config('advertising.fatigue_limit', 100)),
                'effectiveFrom' => null,
            ];
        }

        return $this->presentVersion($version);
    }

    public function ensureInitial(?Account $actor = null): AdvertisingConfigurationVersion
    {
        $runtime = $this->runtime();

        return AdvertisingConfigurationVersion::query()->firstOrCreate(
            ['version' => 1],
            [
                'state' => 'published',
                'minimum_segment_size' => $runtime['minimumSegmentSize'],
                'estimate_rounding_step' => $runtime['estimateRoundingStep'],
                'frequency_window_hours' => $runtime['frequencyWindowHours'],
                'frequency_limit' => $runtime['frequencyLimit'],
                'fatigue_limit' => $runtime['fatigueLimit'],
                'created_by_account_id' => $actor?->id,
                'published_by_account_id' => $actor?->id,
                'effective_from' => now(),
                'published_at' => now(),
            ],
        );
    }

    /**
     * @param array{
     *     minimum_segment_size:int,
     *     estimate_rounding_step:int,
     *     frequency_window_hours:int,
     *     frequency_limit:int,
     *     fatigue_limit:int
     * } $settings
     */
    public function publish(Account $actor, array $settings): AdvertisingConfigurationVersion
    {
        return DB::transaction(function () use ($actor, $settings): AdvertisingConfigurationVersion {
            $current = AdvertisingConfigurationVersion::query()
                ->where('state', 'published')
                ->whereNull('effective_to')
                ->latest('version')
                ->lockForUpdate()
                ->first();
            $normalized = [
                'minimum_segment_size' => (int) $settings['minimum_segment_size'],
                'estimate_rounding_step' => (int) $settings['estimate_rounding_step'],
                'frequency_window_hours' => (int) $settings['frequency_window_hours'],
                'frequency_limit' => (int) $settings['frequency_limit'],
                'fatigue_limit' => (int) $settings['fatigue_limit'],
            ];

            if ($current instanceof AdvertisingConfigurationVersion && $this->sameSettings($current, $normalized)) {
                return $current;
            }

            $now = now();

            if ($current instanceof AdvertisingConfigurationVersion) {
                $current->forceFill(['effective_to' => $now])->save();
            }

            $nextVersion = (int) AdvertisingConfigurationVersion::query()->max('version') + 1;
            $version = AdvertisingConfigurationVersion::query()->create([
                ...$normalized,
                'version' => $nextVersion,
                'state' => 'published',
                'created_by_account_id' => $actor->id,
                'published_by_account_id' => $actor->id,
                'effective_from' => $now,
                'published_at' => $now,
            ]);

            $this->recordEvent(
                actor: $actor,
                eventType: 'advertising.configuration.published',
                resourceType: 'configuration',
                resourceId: $version->id,
                resourceCode: 'matching-controls',
                beforeState: $current?->state,
                afterState: 'published',
                metadata: [
                    'version' => $version->version,
                    'minimum_segment_size' => $version->minimum_segment_size,
                    'estimate_rounding_step' => $version->estimate_rounding_step,
                    'frequency_window_hours' => $version->frequency_window_hours,
                    'frequency_limit' => $version->frequency_limit,
                    'fatigue_limit' => $version->fatigue_limit,
                ],
            );

            return $version;
        });
    }

    public function setPurposeStatus(
        AdvertisingPurpose $purpose,
        string $status,
        Account $actor,
    ): AdvertisingPurpose {
        $this->guardStatus($status);

        return DB::transaction(function () use ($purpose, $status, $actor): AdvertisingPurpose {
            $locked = AdvertisingPurpose::query()->whereKey($purpose->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status;

            if ($before === $status) {
                return $locked;
            }

            if ($status === 'active' && $locked->current_version_id === null) {
                throw ValidationException::withMessages([
                    'status' => 'Une finalité sans version publiée ne peut pas être activée.',
                ]);
            }

            $locked->forceFill(['status' => $status])->save();
            $this->recordEvent(
                actor: $actor,
                eventType: 'advertising.purpose.status_changed',
                resourceType: 'purpose',
                resourceId: $locked->id,
                resourceCode: $locked->code,
                beforeState: $before,
                afterState: $status,
                metadata: ['identity_exported' => false],
            );

            return $locked;
        });
    }

    public function setTaxonomyStatus(
        AdvertisingTaxonomy $taxonomy,
        string $status,
        Account $actor,
    ): AdvertisingTaxonomy {
        $this->guardStatus($status);

        return DB::transaction(function () use ($taxonomy, $status, $actor): AdvertisingTaxonomy {
            $locked = AdvertisingTaxonomy::query()->whereKey($taxonomy->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status;

            if ($before === $status) {
                return $locked;
            }

            if ($status === 'active' && ($locked->sensitive || ! $locked->allowed_for_targeting)) {
                throw ValidationException::withMessages([
                    'status' => 'Une taxonomie sensible ou interdite ne peut pas être activée pour le ciblage.',
                ]);
            }

            $locked->forceFill(['status' => $status])->save();
            $this->recordEvent(
                actor: $actor,
                eventType: 'advertising.taxonomy.status_changed',
                resourceType: 'taxonomy',
                resourceId: $locked->id,
                resourceCode: $locked->code,
                beforeState: $before,
                afterState: $status,
                metadata: [
                    'allowed_for_targeting' => $locked->allowed_for_targeting,
                    'sensitive' => $locked->sensitive,
                    'identity_exported' => false,
                ],
            );

            return $locked;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $limit = 12): array
    {
        return AdvertisingConfigurationVersion::query()
            ->latest('version')
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->map(fn (AdvertisingConfigurationVersion $version): array => $this->presentVersion($version))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function aggregateAudit(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $since = now()->subDays($days);
        $base = AdvertisingMatchAudit::query()->where('occurred_at', '>=', $since);
        $sample = AdvertisingMatchAudit::query()
            ->select(['campaign_id', 'decision', 'reason_codes', 'score_band', 'occurred_at'])
            ->where('occurred_at', '>=', $since)
            ->latest('occurred_at')
            ->limit(5000)
            ->get();
        $reasonCounts = [];

        foreach ($sample as $audit) {
            foreach ($audit->reason_codes as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }

        arsort($reasonCounts);
        $topReasons = [];

        foreach (array_slice($reasonCounts, 0, 8, true) as $code => $count) {
            $topReasons[] = ['code' => (string) $code, 'count' => $count];
        }

        $events = AdvertisingAdministrationEvent::query()
            ->latest('occurred_at')
            ->limit(40)
            ->get()
            ->map(static fn (AdvertisingAdministrationEvent $event): array => [
                'id' => $event->id,
                'eventType' => $event->event_type,
                'resourceType' => $event->resource_type,
                'resourceCode' => $event->resource_code,
                'beforeState' => $event->before_state,
                'afterState' => $event->after_state,
                'occurredAt' => $event->occurred_at->toIso8601String(),
                'actorRecorded' => $event->actor_account_id !== null,
            ])
            ->values()
            ->all();

        return [
            'periodDays' => $days,
            'generatedAt' => now()->toIso8601String(),
            'matches' => [
                'total' => (clone $base)->count(),
                'eligible' => (clone $base)->where('decision', 'eligible')->count(),
                'ineligible' => (clone $base)->where('decision', 'ineligible')->count(),
                'withheld' => (clone $base)->where('decision', 'withheld')->count(),
                'campaigns' => (clone $base)->distinct()->count('campaign_id'),
                'sampleCapped' => $sample->count() === 5000,
            ],
            'scoreBands' => [
                'high' => (clone $base)->where('score_band', 'high')->count(),
                'medium' => (clone $base)->where('score_band', 'medium')->count(),
                'low' => (clone $base)->where('score_band', 'low')->count(),
            ],
            'topReasons' => $topReasons,
            'estimates' => [
                'available' => AdvertisingSegmentEstimate::query()
                    ->where('estimated_at', '>=', $since)
                    ->where('status', 'available')
                    ->count(),
                'withheld' => AdvertisingSegmentEstimate::query()
                    ->where('estimated_at', '>=', $since)
                    ->where('status', 'withheld')
                    ->count(),
            ],
            'events' => $events,
            'privacy' => [
                'subjectHashesReturned' => false,
                'accountIdsReturned' => false,
                'profilesReturned' => false,
            ],
        ];
    }

    private function publishedVersion(): ?AdvertisingConfigurationVersion
    {
        return AdvertisingConfigurationVersion::query()
            ->where('state', 'published')
            ->where('effective_from', '<=', now())
            ->whereNull('effective_to')
            ->latest('version')
            ->first();
    }

    /**
     * @param array{
     *     minimum_segment_size:int,
     *     estimate_rounding_step:int,
     *     frequency_window_hours:int,
     *     frequency_limit:int,
     *     fatigue_limit:int
     * } $settings
     */
    private function sameSettings(
        AdvertisingConfigurationVersion $version,
        array $settings,
    ): bool {
        return $version->minimum_segment_size === $settings['minimum_segment_size']
            && $version->estimate_rounding_step === $settings['estimate_rounding_step']
            && $version->frequency_window_hours === $settings['frequency_window_hours']
            && $version->frequency_limit === $settings['frequency_limit']
            && $version->fatigue_limit === $settings['fatigue_limit'];
    }

    /**
     * @return array{
     *     version:int,
     *     minimumSegmentSize:int,
     *     estimateRoundingStep:int,
     *     frequencyWindowHours:int,
     *     frequencyLimit:int,
     *     fatigueLimit:int,
     *     effectiveFrom:string
     * }
     */
    private function presentVersion(AdvertisingConfigurationVersion $version): array
    {
        return [
            'version' => $version->version,
            'minimumSegmentSize' => $version->minimum_segment_size,
            'estimateRoundingStep' => $version->estimate_rounding_step,
            'frequencyWindowHours' => $version->frequency_window_hours,
            'frequencyLimit' => $version->frequency_limit,
            'fatigueLimit' => $version->fatigue_limit,
            'effectiveFrom' => $version->effective_from->toIso8601String(),
        ];
    }

    private function guardStatus(string $status): void
    {
        if (! in_array($status, ['active', 'suspended'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Le statut demandé n’est pas autorisé.',
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function recordEvent(
        Account $actor,
        string $eventType,
        string $resourceType,
        ?string $resourceId,
        ?string $resourceCode,
        ?string $beforeState,
        ?string $afterState,
        array $metadata,
    ): void {
        AdvertisingAdministrationEvent::query()->create([
            'actor_account_id' => $actor->id,
            'event_type' => $eventType,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_code' => $resourceCode,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
