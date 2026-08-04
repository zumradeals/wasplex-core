<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileSignal;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvertisingProfileSignalService
{
    /** @param  array<string, mixed>  $value */
    public function recordDeclared(
        Account $account,
        AdvertisingTaxonomy $taxonomy,
        array $value,
        ?string $marketCode = null,
    ): AdvertisingProfileSignal {
        $this->guardTaxonomy($taxonomy, false);

        return $this->append(
            account: $account,
            taxonomy: $taxonomy,
            value: $value,
            source: 'declared_by_user',
            status: 'active',
            confidence: 1.0,
            marketCode: $marketCode,
            explanation: 'Information déclarée directement par l’utilisateur.',
            evidence: null,
            requiresUserConfirmation: false,
            createdByAi: false,
            confirmed: true,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $evidence
     */
    public function proposeDerived(
        Account $account,
        AdvertisingTaxonomy $taxonomy,
        array $value,
        string $explanation,
        array $evidence,
        float $confidence,
        ?string $marketCode = null,
    ): AdvertisingProfileSignal {
        $this->guardTaxonomy($taxonomy, true);
        $minimum = (float) config('profile_intelligence.ai.minimum_confidence', 0.75);

        if ($confidence < $minimum || $confidence > 1) {
            throw ValidationException::withMessages([
                'confidence' => "La confiance IA doit être comprise entre {$minimum} et 1.",
            ]);
        }

        if (trim($explanation) === '') {
            throw ValidationException::withMessages([
                'explanation' => 'Toute proposition IA doit être expliquée à l’utilisateur.',
            ]);
        }

        return $this->append(
            account: $account,
            taxonomy: $taxonomy,
            value: $value,
            source: 'derived_by_ai',
            status: 'proposed',
            confidence: $confidence,
            marketCode: $marketCode,
            explanation: $explanation,
            evidence: $evidence,
            requiresUserConfirmation: true,
            createdByAi: true,
            confirmed: false,
        );
    }

    public function decide(
        Account $account,
        AdvertisingProfileSignal $proposal,
        bool $accepted,
    ): AdvertisingProfileSignal {
        if ($proposal->account_id !== $account->id || $proposal->status !== 'proposed') {
            throw ValidationException::withMessages([
                'signal' => 'Cette proposition ne peut pas recevoir cette décision.',
            ]);
        }

        $proposal->loadMissing('taxonomy');

        return $this->append(
            account: $account,
            taxonomy: $proposal->taxonomy,
            value: $proposal->value,
            source: $accepted ? 'confirmed_by_user' : 'corrected_by_user',
            status: $accepted ? 'active' : 'rejected',
            confidence: $accepted ? 1.0 : $proposal->confidence,
            marketCode: $proposal->market_code,
            explanation: $accepted
                ? 'Proposition confirmée par l’utilisateur.'
                : 'Proposition refusée par l’utilisateur.',
            evidence: [
                'proposal_id' => $proposal->id,
                'proposal_source' => $proposal->source,
            ],
            requiresUserConfirmation: false,
            createdByAi: false,
            confirmed: $accepted,
        );
    }

    /** @return array<string, mixed> */
    public function activeFacts(Account $account, string $marketCode): array
    {
        $facts = [];

        foreach ($this->latestSignals($account) as $signal) {
            $signal->loadMissing('taxonomy');
            $taxonomy = $signal->taxonomy;

            if (
                $signal->status !== 'active'
                || $signal->requires_user_confirmation
                || ($signal->expires_at !== null && $signal->expires_at->isPast())
                || $taxonomy->status !== 'active'
                || $taxonomy->sensitive
                || ! $taxonomy->allowed_for_targeting
                || ! $taxonomy->appliesToMarket($marketCode)
            ) {
                continue;
            }

            $facts[$taxonomy->code] = $signal->value;
        }

        return $facts;
    }

    /** @return Collection<string, AdvertisingProfileSignal> */
    public function latestSignals(Account $account): Collection
    {
        /** @var Collection<int, AdvertisingProfileSignal> $history */
        $history = AdvertisingProfileSignal::query()
            ->with('taxonomy')
            ->where('account_id', $account->id)
            ->orderByDesc('version')
            ->orderByDesc('created_at')
            ->get();

        /** @var Collection<string, AdvertisingProfileSignal> $latest */
        $latest = $history
            ->unique('advertising_taxonomy_id')
            ->keyBy('advertising_taxonomy_id');

        return $latest;
    }

    private function guardTaxonomy(AdvertisingTaxonomy $taxonomy, bool $forAi): void
    {
        $forbiddenKinds = config('profile_intelligence.forbidden_signal_kinds', []);

        if (
            $taxonomy->status !== 'active'
            || $taxonomy->sensitive
            || ! $taxonomy->allowed_for_targeting
            || ! $taxonomy->user_visible
            || in_array($taxonomy->signal_kind, $forbiddenKinds, true)
            || ($forAi && $taxonomy->ai_usage_mode === 'forbidden')
        ) {
            throw ValidationException::withMessages([
                'taxonomy' => 'Cette taxonomie ne peut pas alimenter le Profil intelligent.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>|null  $evidence
     */
    private function append(
        Account $account,
        AdvertisingTaxonomy $taxonomy,
        array $value,
        string $source,
        string $status,
        ?float $confidence,
        ?string $marketCode,
        ?string $explanation,
        ?array $evidence,
        bool $requiresUserConfirmation,
        bool $createdByAi,
        bool $confirmed,
    ): AdvertisingProfileSignal {
        return DB::transaction(function () use (
            $account,
            $taxonomy,
            $value,
            $source,
            $status,
            $confidence,
            $marketCode,
            $explanation,
            $evidence,
            $requiresUserConfirmation,
            $createdByAi,
            $confirmed,
        ): AdvertisingProfileSignal {
            $latest = AdvertisingProfileSignal::query()
                ->where('account_id', $account->id)
                ->where('advertising_taxonomy_id', $taxonomy->id)
                ->latest('version')
                ->lockForUpdate()
                ->first();

            return AdvertisingProfileSignal::query()->create([
                'account_id' => $account->id,
                'advertising_taxonomy_id' => $taxonomy->id,
                'version' => $latest instanceof AdvertisingProfileSignal ? $latest->version + 1 : 1,
                'value' => $value,
                'source' => $source,
                'status' => $status,
                'confidence' => $confidence,
                'market_code' => $marketCode === null ? null : strtoupper($marketCode),
                'explanation' => $explanation,
                'evidence' => $evidence,
                'requires_user_confirmation' => $requiresUserConfirmation,
                'created_by_ai' => $createdByAi,
                'observed_at' => now(),
                'confirmed_at' => $confirmed ? now() : null,
                'expires_at' => $taxonomy->default_freshness_days === null
                    ? null
                    : now()->addDays($taxonomy->default_freshness_days),
                'replaces_signal_id' => $latest?->id,
            ]);
        });
    }
}
