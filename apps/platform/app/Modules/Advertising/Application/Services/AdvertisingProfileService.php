<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Domain\Events\AdvertisingProfileUpdated;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileAnswer;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvertisingProfileService
{
    public function __construct(private readonly AdvertisingConsentService $consents) {}

    /**
     * @return array{
     *     completion:int,
     *     answered:int,
     *     total:int,
     *     questions:array<int, array<string, mixed>>
     * }
     */
    public function profile(Account $account): array
    {
        $questions = AdvertisingProfileQuestion::query()
            ->with(['taxonomy', 'currentVersion'])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->filter(static fn (AdvertisingProfileQuestion $question): bool => $question->currentVersion !== null
                && $question->currentVersion->state === 'published'
                && $question->taxonomy->status === 'active'
                && ! $question->taxonomy->sensitive)
            ->values();
        $latest = $this->latestAnswers($account);
        $answered = 0;

        $presented = $questions->map(function (AdvertisingProfileQuestion $question) use ($latest, &$answered): array {
            $version = $question->currentVersion;
            $answer = $latest->get($question->id);
            $active = $answer instanceof AdvertisingProfileAnswer
                && $answer->status === 'active'
                && ($answer->expires_at === null || $answer->expires_at->isFuture());

            if ($active) {
                $answered++;
            }

            return [
                'code' => $question->code,
                'category' => $question->taxonomy->category,
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
        $total = $questions->count();

        return [
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

            if (
                $version === null
                || $version->state !== 'published'
                || $question->taxonomy->status !== 'active'
                || $question->taxonomy->sensitive
                || ! $question->taxonomy->allowed_for_targeting
            ) {
                throw ValidationException::withMessages([
                    'question' => 'Cette question ne peut pas alimenter le profil publicitaire.',
                ]);
            }

            $allowed = collect($version->options ?? [])
                ->map(static fn (array $option): string => $option['value'])
                ->filter()
                ->values();

            if ($allowed->isNotEmpty() && ! $allowed->contains($selected)) {
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

    /** @return array<string, string> */
    public function matchingFacts(Account $account): array
    {
        if (! $this->consents->hasActive($account, [
            'advertising_personalization',
            'smart_profile_usage',
        ])) {
            return [];
        }

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

        return $facts;
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
