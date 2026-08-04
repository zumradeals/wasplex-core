<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegment;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegmentEstimate;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegmentRule;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class AdvertisingSegmentService
{
    public function __construct(
        private AdvertisingProfileService $profiles,
        private EconomicClassEligibilityProjection $economicClasses,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function allowedTaxonomies(): array
    {
        return AdvertisingTaxonomy::query()
            ->with(['questions.currentVersion'])
            ->where('status', 'active')
            ->where('allowed_for_targeting', true)
            ->where('sensitive', false)
            ->orderBy('category')
            ->orderBy('label')
            ->get()
            ->map(function (AdvertisingTaxonomy $taxonomy): array {
                $question = $taxonomy->questions->first(
                    static fn (AdvertisingProfileQuestion $candidate): bool => $candidate->status === 'active'
                        && $candidate->currentVersion !== null
                        && $candidate->currentVersion->state === 'published',
                );

                if (! $question instanceof AdvertisingProfileQuestion || $question->currentVersion === null) {
                    return [
                        'code' => $taxonomy->code,
                        'category' => $taxonomy->category,
                        'label' => $taxonomy->label,
                        'valueType' => $taxonomy->value_type,
                        'options' => [],
                        'purposeCodes' => [],
                    ];
                }

                $version = $question->currentVersion;

                return [
                    'code' => $taxonomy->code,
                    'category' => $taxonomy->category,
                    'label' => $taxonomy->label,
                    'valueType' => $taxonomy->value_type,
                    'options' => $version->options ?? [],
                    'purposeCodes' => $version->purpose_codes ?? [],
                ];
            })
            ->filter(static fn (array $taxonomy): bool => $taxonomy['options'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{taxonomy_code:string,operator?:string,values:array<int, string>,required?:bool}>  $rules
     */
    public function configure(Campaign $campaign, Account $actor, array $rules): AdvertisingSegment
    {
        if (! in_array($campaign->status, ['draft', 'quoted', 'changes_requested'], true)) {
            throw ValidationException::withMessages([
                'segment' => 'Le ciblage ne peut plus être modifié après le financement ou la décision administrative.',
            ]);
        }

        $version = $campaign->activeVersion()->first();

        if (! $version instanceof CampaignVersion) {
            throw ValidationException::withMessages([
                'segment' => 'La version active de la campagne est introuvable.',
            ]);
        }

        $normalized = $this->normalizeRules($rules);

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'rules' => 'Ajoutez au moins un critère publicitaire autorisé.',
            ]);
        }

        $ruleVersion = hash('sha256', $this->encode($normalized));

        return DB::transaction(function () use ($campaign, $version, $actor, $normalized, $ruleVersion): AdvertisingSegment {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->active_version_id !== $version->id) {
                throw ValidationException::withMessages([
                    'segment' => 'La campagne a changé pendant la préparation du ciblage. Rechargez la page.',
                ]);
            }

            $existing = AdvertisingSegment::query()
                ->where('campaign_version_id', $version->id)
                ->where('status', 'active')
                ->latest('version')
                ->first();

            if ($existing instanceof AdvertisingSegment && $existing->rule_version === $ruleVersion) {
                return $existing->load('rules.taxonomy');
            }

            AdvertisingSegment::query()
                ->where('campaign_version_id', $version->id)
                ->where('status', 'active')
                ->update(['status' => 'suspended', 'updated_at' => now()]);

            $nextVersion = (int) AdvertisingSegment::query()
                ->where('campaign_version_id', $version->id)
                ->max('version') + 1;
            $segment = AdvertisingSegment::query()->create([
                'campaign_id' => $locked->id,
                'campaign_version_id' => $version->id,
                'version' => $nextVersion,
                'status' => 'active',
                'rule_version' => $ruleVersion,
                'minimum_size' => max(1, (int) config('advertising.minimum_segment_size', 25)),
                'created_by_account_id' => $actor->id,
            ]);

            foreach ($normalized as $index => $rule) {
                AdvertisingSegmentRule::query()->create([
                    'advertising_segment_id' => $segment->id,
                    'advertising_taxonomy_id' => $rule['taxonomy_id'],
                    'operator' => $rule['operator'],
                    'expected_values' => $rule['expected_values'],
                    'required' => $rule['required'],
                    'purpose_codes' => $rule['purpose_codes'],
                    'sort_order' => $index + 1,
                ]);
            }

            DB::afterCommit(static fn () => event('advertising.segment_configured', [
                $segment->id,
                $locked->id,
                $segment->rule_version,
            ]));

            return $segment->load('rules.taxonomy');
        });
    }

    public function activeForCampaign(Campaign $campaign): ?AdvertisingSegment
    {
        if (! is_string($campaign->active_version_id)) {
            return null;
        }

        return AdvertisingSegment::query()
            ->with(['rules.taxonomy', 'campaignVersion'])
            ->where('campaign_id', $campaign->id)
            ->where('campaign_version_id', $campaign->active_version_id)
            ->where('status', 'active')
            ->latest('version')
            ->first();
    }

    public function estimate(AdvertisingSegment $segment, ?Account $actor = null): AdvertisingSegmentEstimate
    {
        $segment->loadMissing(['rules.taxonomy', 'campaignVersion']);
        $estimateKey = hash('sha256', implode('|', [
            $segment->id,
            $segment->rule_version,
            (string) $segment->minimum_size,
            now()->utc()->format('Y-m-d-H'),
        ]));
        $existing = AdvertisingSegmentEstimate::query()
            ->where('estimate_key', $estimateKey)
            ->first();

        if ($existing instanceof AdvertisingSegmentEstimate) {
            return $existing;
        }

        $rawCount = 0;
        $accounts = Account::query()
            ->whereHas('spaces', static function (Builder $query): void {
                $query->where('kind', 'user')->where('status', 'active');
            })
            ->orderBy('id')
            ->lazyById(200);

        foreach ($accounts as $account) {
            if ($this->matchesAccount($account, $segment)) {
                $rawCount++;
            }
        }

        $threshold = max(
            1,
            $segment->minimum_size,
            (int) config('advertising.minimum_segment_size', 25),
        );
        $step = max(1, (int) config('advertising.estimate_rounding_step', 10));
        $available = $rawCount >= $threshold;
        $rounded = $available ? (int) (round($rawCount / $step) * $step) : null;
        $protectedMin = $rounded === null
            ? null
            : max($threshold, max(0, $rounded - $step));
        $protectedMax = $rounded === null ? null : $rounded + $step;

        $estimate = AdvertisingSegmentEstimate::query()->create([
            'advertising_segment_id' => $segment->id,
            'estimate_key' => $estimateKey,
            'status' => $available ? 'available' : 'withheld',
            'raw_count' => $rawCount,
            'protected_min' => $protectedMin,
            'protected_max' => $protectedMax,
            'rounded_count' => $rounded,
            'threshold' => $threshold,
            'bucket' => $rounded === null ? 'below_threshold' : $this->bucket($rounded),
            'reason_code' => $available ? null : 'segment_below_privacy_threshold',
            'queried_by_account_id' => $actor?->id,
            'estimated_at' => now(),
            'metadata' => [
                'rounding_step' => $step,
                'rule_version' => $segment->rule_version,
                'members_exported' => false,
            ],
        ]);

        DB::afterCommit(static fn () => event('advertising.segment_estimated', [
            $segment->id,
            $estimate->id,
            $estimate->status,
        ]));

        return $estimate;
    }

    /** @return array<string, mixed> */
    public function presentEstimate(AdvertisingSegmentEstimate $estimate): array
    {
        return [
            'id' => $estimate->id,
            'status' => $estimate->status,
            'threshold' => $estimate->threshold,
            'bucket' => $estimate->bucket,
            'approximateCount' => $estimate->rounded_count,
            'protectedRange' => $estimate->status === 'available'
                ? ['min' => $estimate->protected_min, 'max' => $estimate->protected_max]
                : null,
            'reasonCode' => $estimate->reason_code,
            'estimatedAt' => $estimate->estimated_at->toIso8601String(),
            'membersExported' => false,
        ];
    }

    private function matchesAccount(Account $account, AdvertisingSegment $segment): bool
    {
        $version = $segment->campaignVersion;
        $selectedClasses = $version->selected_classes;
        $economicClass = $this->economicClasses->code($account);

        if ($selectedClasses !== [] && ! in_array($economicClass, $selectedClasses, true)) {
            return false;
        }

        $facts = $this->profiles->matchingFacts($account);

        foreach ($segment->rules as $rule) {
            $actual = $facts[$rule->taxonomy->code] ?? null;
            $matched = is_string($actual) && in_array($actual, $rule->expected_values, true);

            if ($rule->required && ! $matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{taxonomy_code:string,operator?:string,values:array<int, string>,required?:bool}>  $rules
     * @return array<int, array{
     *     taxonomy_id:string,
     *     taxonomy_code:string,
     *     operator:string,
     *     expected_values:array<int, string>,
     *     required:bool,
     *     purpose_codes:array<int, string>
     * }>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rules as $input) {
            $taxonomyCode = trim($input['taxonomy_code']);

            if ($taxonomyCode === '' || isset($seen[$taxonomyCode])) {
                throw ValidationException::withMessages([
                    'rules' => 'Chaque taxonomie doit être renseignée une seule fois.',
                ]);
            }

            $taxonomy = AdvertisingTaxonomy::query()
                ->with(['questions.currentVersion'])
                ->where('code', $taxonomyCode)
                ->where('status', 'active')
                ->where('allowed_for_targeting', true)
                ->where('sensitive', false)
                ->first();

            if (! $taxonomy instanceof AdvertisingTaxonomy) {
                throw ValidationException::withMessages([
                    'rules' => "La taxonomie {$taxonomyCode} n’est pas autorisée au ciblage.",
                ]);
            }

            $question = $taxonomy->questions->first(
                static fn (AdvertisingProfileQuestion $candidate): bool => $candidate->status === 'active'
                    && $candidate->currentVersion !== null
                    && $candidate->currentVersion->state === 'published',
            );
            $questionVersion = $question?->currentVersion;

            if ($questionVersion === null) {
                throw ValidationException::withMessages([
                    'rules' => "La taxonomie {$taxonomyCode} ne possède aucune question publiée.",
                ]);
            }

            $operator = (string) ($input['operator'] ?? 'equals');

            if (! in_array($operator, ['equals', 'in'], true)) {
                throw ValidationException::withMessages([
                    'rules' => 'Seuls les opérateurs equals et in sont autorisés.',
                ]);
            }

            $values = array_values(array_unique(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $input['values'],
            ), static fn (string $value): bool => $value !== '')));
            $allowedValues = array_values(array_map(
                static fn (array $option): string => (string) $option['value'],
                $questionVersion->options ?? [],
            ));

            if ($values === [] || array_diff($values, $allowedValues) !== []) {
                throw ValidationException::withMessages([
                    'rules' => "Une valeur du critère {$taxonomyCode} n’est pas autorisée.",
                ]);
            }

            $normalized[] = [
                'taxonomy_id' => $taxonomy->id,
                'taxonomy_code' => $taxonomy->code,
                'operator' => $operator,
                'expected_values' => $values,
                'required' => (bool) ($input['required'] ?? true),
                'purpose_codes' => array_values($questionVersion->purpose_codes),
            ];
            $seen[$taxonomyCode] = true;
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => $left['taxonomy_code'] <=> $right['taxonomy_code'],
        );

        return $normalized;
    }

    private function bucket(int $count): string
    {
        return match (true) {
            $count < 100 => 'small',
            $count < 1000 => 'medium',
            $count < 10000 => 'large',
            default => 'very_large',
        };
    }

    /** @param array<int, array<string, mixed>> $value */
    private function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'rules' => 'Les règles de ciblage ne peuvent pas être normalisées.',
            ]);
        }
    }
}
