<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingFrequencyCounter;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatchAudit;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegment;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegmentRule;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class CampaignEligibilityService
{
    public function __construct(
        private AdvertisingProfileService $profiles,
        private AdvertisingConsentService $consents,
        private AdvertisingSegmentService $segments,
        private EconomicClassEligibilityProjection $economicClasses,
    ) {}

    public function evaluate(Account $account, Campaign $campaign): AdvertisingMatch
    {
        $campaign->loadMissing('activeVersion');
        $version = $campaign->activeVersion;
        $segment = $this->segments->activeForCampaign($campaign);

        if (! $version instanceof CampaignVersion || ! $segment instanceof AdvertisingSegment) {
            throw ValidationException::withMessages([
                'campaign' => 'La campagne ne possède pas de version et de segment actifs évaluables.',
            ]);
        }

        $segment->loadMissing(['rules.taxonomy', 'estimates']);
        $facts = $this->profiles->matchingFacts($account);
        $economicClass = $this->economicClasses->code($account);
        $frequency = $this->frequencyState($account, $campaign);
        $stateHash = hash('sha256', $this->encode([
            'campaign_status' => $campaign->status,
            'campaign_version' => $version->id,
            'segment_rule_version' => $segment->rule_version,
            'facts' => $facts,
            'economic_class' => $economicClass,
            'frequency' => $frequency,
            'evaluated_hour' => now()->utc()->format('Y-m-d-H'),
        ]));
        $idempotencyKey = hash('sha256', implode('|', [
            $account->id,
            $campaign->id,
            $stateHash,
        ]));
        $existing = AdvertisingMatch::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof AdvertisingMatch) {
            return $existing;
        }

        $decision = 'eligible';
        $score = 0;
        $explanations = [];
        $exclusions = [];

        if ($campaign->status !== 'approved') {
            $decision = 'ineligible';
            $exclusions[] = $campaign->status === 'suspended'
                ? 'campaign_suspended'
                : 'campaign_not_approved';
        } else {
            $score += 1000;
        }

        if ($decision === 'eligible' && ! $this->withinPeriod($version)) {
            $decision = 'ineligible';
            $exclusions[] = 'campaign_outside_period';
        } elseif ($decision === 'eligible') {
            $score += 500;
        }

        $requiredPurposes = $this->requiredPurposes($segment);

        if ($decision === 'eligible' && ! $this->consents->hasActive($account, $requiredPurposes)) {
            $decision = 'ineligible';
            $exclusions[] = 'required_consent_inactive';
        } elseif ($decision === 'eligible') {
            $score += 1000;
            foreach ($requiredPurposes as $purpose) {
                $explanations[] = "consent:{$purpose}";
            }
        }

        if (
            $decision === 'eligible'
            && $version->selected_classes !== []
            && ! in_array($economicClass, $version->selected_classes, true)
        ) {
            $decision = 'ineligible';
            $exclusions[] = 'economic_class_not_selected';
        } elseif ($decision === 'eligible') {
            $score += 1500;
            $explanations[] = "class:{$economicClass}";
        }

        if ($decision === 'eligible') {
            [$rulesMatched, $ruleScore, $ruleExplanations, $ruleExclusions] = $this->evaluateRules(
                $segment,
                $facts,
            );
            $score += $ruleScore;
            $explanations = array_values(array_unique(array_merge($explanations, $ruleExplanations)));

            if (! $rulesMatched) {
                $decision = 'ineligible';
                $exclusions = array_values(array_unique(array_merge($exclusions, $ruleExclusions)));
            }
        }

        if ($decision === 'eligible') {
            $estimate = $this->segments->estimate($segment);

            if ($estimate->status !== 'available') {
                $decision = 'withheld';
                $exclusions[] = 'privacy_threshold_not_met';
            } else {
                $score += 1000;
            }
        }

        if ($decision === 'eligible' && $frequency['impressions'] >= $frequency['limit']) {
            $decision = 'ineligible';
            $exclusions[] = 'frequency_limit_reached';
        }

        if ($decision === 'eligible' && $frequency['fatigueScore'] >= $frequency['fatigueLimit']) {
            $decision = 'withheld';
            $exclusions[] = 'fatigue_safety_hold';
        }

        if ($decision === 'eligible') {
            $score += 1000;
        }

        $score = min(10000, max(0, $score));
        $scoreBand = $decision === 'eligible' ? $this->scoreBand($score) : null;

        return DB::transaction(function () use (
            $account,
            $campaign,
            $version,
            $segment,
            $idempotencyKey,
            $decision,
            $score,
            $scoreBand,
            $explanations,
            $exclusions,
            $frequency,
        ): AdvertisingMatch {
            $match = AdvertisingMatch::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'account_id' => $account->id,
                    'campaign_id' => $campaign->id,
                    'campaign_version_id' => $version->id,
                    'advertising_segment_id' => $segment->id,
                    'decision' => $decision,
                    'score_basis_points' => $score,
                    'score_band' => $scoreBand,
                    'rule_version' => $segment->rule_version,
                    'explanation_tokens' => array_values(array_unique($explanations)),
                    'exclusion_codes' => array_values(array_unique($exclusions)),
                    'frequency_state' => $frequency,
                    'evaluated_at' => now(),
                ],
            );

            if ($match->wasRecentlyCreated) {
                AdvertisingMatchAudit::query()->create([
                    'advertising_match_id' => $match->id,
                    'campaign_id' => $campaign->id,
                    'subject_hash' => $this->subjectHash($account),
                    'decision' => $match->decision,
                    'reason_codes' => $match->decision === 'eligible'
                        ? ['eligible_rules_satisfied']
                        : $match->exclusion_codes,
                    'score_band' => $match->score_band,
                    'occurred_at' => now(),
                    'metadata' => [
                        'rule_version' => $segment->rule_version,
                        'profile_exported' => false,
                        'financial_operation_created' => false,
                    ],
                ]);

                DB::afterCommit(static fn () => event(
                    $match->decision === 'eligible'
                        ? 'advertising.campaign_matched'
                        : 'advertising.campaign_match_withheld',
                    [$match->id, $campaign->id, $match->decision],
                ));
            }

            return $match;
        });
    }

    /**
     * @param  array<string, string>  $facts
     * @return array{0:bool,1:int,2:array<int, string>,3:array<int, string>}
     */
    private function evaluateRules(AdvertisingSegment $segment, array $facts): array
    {
        $matched = true;
        $explanations = [];
        $exclusions = [];
        $ruleCount = max(1, $segment->rules->count());
        $scorePerRule = intdiv(4000, $ruleCount);
        $score = 0;

        foreach ($segment->rules as $rule) {
            $actual = $facts[$rule->taxonomy->code] ?? null;
            $ruleMatched = is_string($actual) && in_array($actual, $rule->expected_values, true);

            if ($ruleMatched) {
                $score += $scorePerRule;
                $explanations[] = $this->explanationToken($rule, $actual);

                continue;
            }

            if ($rule->required) {
                $matched = false;
                $exclusions[] = "rule_not_matched:{$rule->taxonomy->code}";
            }
        }

        return [$matched, $score, $explanations, $exclusions];
    }

    /** @return array<int, string> */
    private function requiredPurposes(AdvertisingSegment $segment): array
    {
        $purposes = ['advertising_personalization', 'smart_profile_usage'];

        foreach ($segment->rules as $rule) {
            foreach ($rule->purpose_codes as $purpose) {
                if ($purpose !== '') {
                    $purposes[] = $purpose;
                }
            }
        }

        return array_values(array_unique($purposes));
    }

    /** @return array{impressions:int,limit:int,fatigueScore:int,fatigueLimit:int,windowStart:string,windowEnd:string} */
    private function frequencyState(Account $account, Campaign $campaign): array
    {
        $hours = max(1, (int) config('advertising.frequency_window_hours', 24));
        $now = now();
        $counter = AdvertisingFrequencyCounter::query()
            ->where('account_id', $account->id)
            ->where('campaign_id', $campaign->id)
            ->where('window_start', '<=', $now)
            ->where('window_end', '>', $now)
            ->latest('window_start')
            ->first();

        if ($counter instanceof AdvertisingFrequencyCounter) {
            return [
                'impressions' => $counter->impressions,
                'limit' => max(1, (int) config('advertising.frequency_limit', 3)),
                'fatigueScore' => $counter->fatigue_score,
                'fatigueLimit' => max(1, (int) config('advertising.fatigue_limit', 100)),
                'windowStart' => $counter->window_start->toIso8601String(),
                'windowEnd' => $counter->window_end->toIso8601String(),
            ];
        }

        $windowSeconds = $hours * 3600;
        $windowStartTimestamp = intdiv($now->timestamp, $windowSeconds) * $windowSeconds;
        $windowStart = $now->copy()->setTimestamp($windowStartTimestamp)->utc();
        $windowEnd = $windowStart->copy()->addSeconds($windowSeconds);

        return [
            'impressions' => 0,
            'limit' => max(1, (int) config('advertising.frequency_limit', 3)),
            'fatigueScore' => 0,
            'fatigueLimit' => max(1, (int) config('advertising.fatigue_limit', 100)),
            'windowStart' => $windowStart->toIso8601String(),
            'windowEnd' => $windowEnd->toIso8601String(),
        ];
    }

    private function withinPeriod(CampaignVersion $version): bool
    {
        $today = now()->startOfDay();

        return ($version->starts_on === null || $version->starts_on->startOfDay()->lessThanOrEqualTo($today))
            && ($version->ends_on === null || $version->ends_on->endOfDay()->greaterThanOrEqualTo($today));
    }

    private function explanationToken(AdvertisingSegmentRule $rule, string $actual): string
    {
        return match ($rule->taxonomy->category) {
            'territory' => "territory:{$rule->taxonomy->code}:{$actual}",
            'usage' => "usage:{$rule->taxonomy->code}:{$actual}",
            'interest' => "interest:{$rule->taxonomy->code}:{$actual}",
            'project' => "project:{$rule->taxonomy->code}:{$actual}",
            default => "declared:{$rule->taxonomy->code}:{$actual}",
        };
    }

    private function scoreBand(int $score): string
    {
        return match (true) {
            $score >= 8000 => 'high',
            $score >= 6000 => 'medium',
            default => 'low',
        };
    }

    private function subjectHash(Account $account): string
    {
        $key = (string) config('advertising.subject_hash_key', config('app.key'));

        return hash_hmac('sha256', $account->id, $key === '' ? 'wasplex-p008' : $key);
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'campaign' => 'L’état du Matching ne peut pas être normalisé.',
            ]);
        }
    }
}
