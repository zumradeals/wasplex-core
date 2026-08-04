<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Domain\Events\AdvertisingProfileUpdated;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileAnswer;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileSignal;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSector;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvertisingProfileService
{
    public function __construct(
        private readonly AdvertisingConsentService $consents,
        private readonly AdvertisingProfileSignalService $signals,
    ) {}

    /**
     * @return array{
     *     market:array{code:string,name:string},
     *     summary:array{activeInformation:int,sectorsExplored:int,availableSectors:int,pendingSuggestions:int},
     *     sectors:array<int, array<string, mixed>>,
     *     completion:int,
     *     answered:int,
     *     total:int,
     *     questions:array<int, array<string, mixed>>
     * }
     */
    public function profile(Account $account): array
    {
        $marketCode = strtoupper((string) config('profile_intelligence.default_market', 'ML'));
        $locale = (string) config('profile_intelligence.default_locale', 'fr');
        $questions = AdvertisingProfileQuestion::query()
            ->with(['taxonomy.sector.translations', 'currentVersion'])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->filter(static fn (AdvertisingProfileQuestion $question): bool => $question->currentVersion !== null
                && $question->currentVersion->state === 'published'
                && $question->taxonomy->status === 'active'
                && $question->taxonomy->user_visible
                && ! $question->taxonomy->sensitive
                && $question->taxonomy->appliesToMarket($marketCode))
            ->values();
        $latest = $this->latestAnswers($account);
        $answered = 0;

        $presented = $questions->map(function (AdvertisingProfileQuestion $question) use (
            $latest,
            $locale,
            &$answered,
        ): array {
            $version = $question->currentVersion;
            $answer = $latest->get($question->id);
            $sector = $question->taxonomy->sector;
            $sectorCode = $sector instanceof AdvertisingSector ? $sector->code : 'general';
            $sectorLabel = $sector instanceof AdvertisingSector
                ? $sector->translatedName($locale)
                : 'Profil général';

            if ($version === null) {
                return [
                    'code' => $question->code,
                    'category' => $question->taxonomy->category,
                    'signalKind' => $question->taxonomy->signal_kind,
                    'sectorCode' => $sectorCode,
                    'sectorLabel' => $sectorLabel,
                    'taxonomyCode' => $question->taxonomy->code,
                    'taxonomyLabel' => $question->taxonomy->label,
                    'prompt' => '',
                    'helpText' => '',
                    'privacyNote' => '',
                    'optional' => true,
                    'options' => [],
                    'purposeCodes' => [],
                    'answer' => null,
                    'answerVersion' => null,
                    'answeredAt' => null,
                    'expiresAt' => null,
                    'status' => null,
                ];
            }

            $active = $answer instanceof AdvertisingProfileAnswer
                && $answer->status === 'active'
                && ($answer->expires_at === null || $answer->expires_at->isFuture());

            if ($active) {
                $answered++;
            }

            return [
                'code' => $question->code,
                'category' => $question->taxonomy->category,
                'signalKind' => $question->taxonomy->signal_kind,
                'sectorCode' => $sectorCode,
                'sectorLabel' => $sectorLabel,
                'taxonomyCode' => $question->taxonomy->code,
                'taxonomyLabel' => $question->taxonomy->label,
                'prompt' => $version->prompt,
                'helpText' => $version->help_text,
                'privacyNote' => $version->privacy_note,
                'optional' => $version->optional,
                'options' => $version->options ?? [],
                'purposeCodes' => $version->purpose_codes,
                'answer' => $active ? ($answer?->value['selected'] ?? null) : null,
                'answerVersion' => $answer?->version,
                'answeredAt' => $answer?->answered_at->toIso8601String(),
                'expiresAt' => $answer?->expires_at?->toIso8601String(),
                'status' => $answer?->status,
            ];
        })->all();
        $presentedCollection = new SupportCollection($presented);
        $latestSignals = $this->signals->latestSignals($account);
        $activeSignalCount = $latestSignals->filter(
            static fn (AdvertisingProfileSignal $signal): bool => $signal->status === 'active'
                && ! $signal->requires_user_confirmation
                && ($signal->expires_at === null || $signal->expires_at->isFuture()),
        )->count();
        $pendingSuggestions = $latestSignals->where('status', 'proposed')->count();
        $sectors = AdvertisingSector::query()
            ->with('translations')
            ->where('status', 'active')
            ->where('allowed_for_targeting', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(function (AdvertisingSector $sector) use ($presentedCollection, $locale): array {
                $sectorQuestions = $presentedCollection->where('sectorCode', $sector->code);
                $sectorAnswered = $sectorQuestions->whereNotNull('answer')->count();

                return [
                    'code' => $sector->code,
                    'name' => $sector->translatedName($locale),
                    'icon' => $sector->icon,
                    'questions' => $sectorQuestions->count(),
                    'answered' => $sectorAnswered,
                    'explored' => $sectorAnswered > 0,
                ];
            })
            ->values()
            ->all();
        $total = $questions->count();
        $sectorsExplored = count(array_filter(
            $sectors,
            static fn (array $sector): bool => $sector['explored'],
        ));

        return [
            'market' => [
                'code' => $marketCode,
                'name' => $marketCode === 'ML' ? 'Mali' : $marketCode,
            ],
            'summary' => [
                'activeInformation' => $answered + $activeSignalCount,
                'sectorsExplored' => $sectorsExplored,
                'availableSectors' => count($sectors),
                'pendingSuggestions' => $pendingSuggestions,
            ],
            'sectors' => $sectors,
            // Compatibilité transitoire P008 : l’interface P008-R n’utilise plus ce faux score.
            'completion' => $total === 0 ? 0 : (int) round(($answered / $total) * 100),
            'answered' => $answered,
            'total' => $total,
            'questions' => $presented,
        ];
    }

    public function answer(Account $account, string $questionCode, string $selected): AdvertisingProfileAnswer
    {
        return DB::transaction(function () use ($account, $questionCode, $selected): AdvertisingProfileAnswer {
            $question = AdvertisingProfileQuestion::query()
                ->with(['taxonomy', 'currentVersion'])
                ->where('code', $questionCode)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $version = $question->currentVersion;
            $marketCode = strtoupper((string) config('profile_intelligence.default_market', 'ML'));

            if (
                $version === null
                || $version->state !== 'published'
                || $question->taxonomy->status !== 'active'
                || ! $question->taxonomy->user_visible
                || ! $question->taxonomy->appliesToMarket($marketCode)
                || $question->taxonomy->sensitive
                || ! $question->taxonomy->allowed_for_targeting
            ) {
                throw ValidationException::withMessages([
                    'question' => 'Cette question ne peut pas alimenter le profil publicitaire.',
                ]);
            }

            $allowed = array_values(array_filter(array_map(
                static fn (array $option): string => $option['value'],
                $version->options ?? [],
            )));

            if ($allowed !== [] && ! in_array($selected, $allowed, true)) {
                throw ValidationException::withMessages([
                    'answer' => 'La réponse choisie ne fait pas partie des options autorisées.',
                ]);
            }

            $latest = AdvertisingProfileAnswer::query()
                ->where('account_id', $account->id)
                ->where('advertising_profile_question_id', $question->id)
                ->latest('version')
                ->first();

            if (
                $latest instanceof AdvertisingProfileAnswer
                && $latest->status === 'active'
                && ($latest->value['selected'] ?? null) === $selected
                && ($latest->expires_at === null || $latest->expires_at->isFuture())
            ) {
                return $latest;
            }

            $nextVersion = $latest instanceof AdvertisingProfileAnswer
                ? $latest->version + 1
                : 1;
            $provenance = $latest instanceof AdvertisingProfileAnswer
                ? 'corrected'
                : 'declared_by_user';
            $replacesAnswerId = $latest instanceof AdvertisingProfileAnswer
                ? $latest->id
                : null;

            $answer = AdvertisingProfileAnswer::query()->create([
                'account_id' => $account->id,
                'advertising_profile_question_id' => $question->id,
                'advertising_taxonomy_id' => $question->taxonomy->id,
                'version' => $nextVersion,
                'value' => ['selected' => $selected],
                'provenance' => $provenance,
                'status' => 'active',
                'answered_at' => now(),
                'confirmed_at' => now(),
                'expires_at' => $version->freshness_days === null
                    ? null
                    : now()->addDays($version->freshness_days),
                'replaces_answer_id' => $replacesAnswerId,
            ]);

            DB::afterCommit(static fn () => event(new AdvertisingProfileUpdated(
                $account->id,
                $question->code,
                $answer->id,
            )));

            return $answer;
        });
    }

    public function remove(Account $account, string $questionCode): AdvertisingProfileAnswer
    {
        return DB::transaction(function () use ($account, $questionCode): AdvertisingProfileAnswer {
            $question = AdvertisingProfileQuestion::query()
                ->where('code', $questionCode)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $latest = AdvertisingProfileAnswer::query()
                ->where('account_id', $account->id)
                ->where('advertising_profile_question_id', $question->id)
                ->latest('version')
                ->first();

            if ($latest instanceof AdvertisingProfileAnswer && $latest->status === 'deleted') {
                return $latest;
            }

            $nextVersion = $latest instanceof AdvertisingProfileAnswer
                ? $latest->version + 1
                : 1;
            $replacesAnswerId = $latest instanceof AdvertisingProfileAnswer
                ? $latest->id
                : null;

            $answer = AdvertisingProfileAnswer::query()->create([
                'account_id' => $account->id,
                'advertising_profile_question_id' => $question->id,
                'advertising_taxonomy_id' => $question->advertising_taxonomy_id,
                'version' => $nextVersion,
                'value' => ['selected' => null],
                'provenance' => 'corrected',
                'status' => 'deleted',
                'answered_at' => now(),
                'replaces_answer_id' => $replacesAnswerId,
            ]);

            DB::afterCommit(static fn () => event(new AdvertisingProfileUpdated(
                $account->id,
                $question->code,
                $answer->id,
            )));

            return $answer;
        });
    }

    /** @return array<string, mixed> */
    public function matchingFacts(Account $account): array
    {
        if (! $this->consents->hasActive($account, [
            'advertising_personalization',
            'smart_profile_usage',
        ])) {
            return [];
        }

        $marketCode = strtoupper((string) config('profile_intelligence.default_market', 'ML'));
        $approximateLocationAllowed = $this->consents->hasActive(
            $account,
            ['approximate_location_targeting'],
        );
        $answers = $this->latestAnswers($account);
        $facts = [];

        foreach ($answers as $answer) {
            $answer->loadMissing('taxonomy');

            if (
                $answer->status !== 'active'
                || ($answer->expires_at !== null && $answer->expires_at->isPast())
                || $answer->taxonomy->sensitive
                || ! $answer->taxonomy->allowed_for_targeting
                || ! $answer->taxonomy->appliesToMarket($marketCode)
            ) {
                continue;
            }

            if ($answer->taxonomy->category === 'territory' && ! $approximateLocationAllowed) {
                continue;
            }

            $selected = $answer->value['selected'] ?? null;

            if (is_string($selected) && $selected !== '') {
                $facts[$answer->taxonomy->code] = $selected;
            }
        }

        return [
            ...$facts,
            ...$this->signals->activeFacts($account, $marketCode),
        ];
    }

    /** @return Collection<string, AdvertisingProfileAnswer> */
    private function latestAnswers(Account $account): Collection
    {
        /** @var Collection<int, AdvertisingProfileAnswer> $history */
        $history = AdvertisingProfileAnswer::query()
            ->with('taxonomy')
            ->where('account_id', $account->id)
            ->orderByDesc('version')
            ->orderByDesc('answered_at')
            ->get();

        /** @var Collection<string, AdvertisingProfileAnswer> $latest */
        $latest = $history
            ->unique('advertising_profile_question_id')
            ->keyBy('advertising_profile_question_id');

        return $latest;
    }
}
