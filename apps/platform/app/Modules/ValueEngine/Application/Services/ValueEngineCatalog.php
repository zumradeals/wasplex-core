<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\ValueEngine\Infrastructure\Models\ValueEventDefinition;
use App\Modules\ValueEngine\Infrastructure\Models\ValueRuleVersion;

final readonly class ValueEngineCatalog
{
    public function __construct(private LedgerCatalog $ledger) {}

    public function ensureAdvertisingCore(): void
    {
        $this->ledger->createAccountType(
            'REVENUE',
            'Produit',
            EntryDirection::Credit,
            'Produits reconnus par Wasplex.',
        );
        $this->ledger->createJournal('VALUE_ENGINE', 'Super Moteur unifié de valeur');
        $this->revenueAccountId();

        $event = ValueEventDefinition::query()->updateOrCreate(
            ['code' => (string) config('value-engine.advertising_event_code')],
            [
                'source_module' => 'feed',
                'status' => 'active',
                'requires_reservation' => true,
                'proof_type' => 'qualified_attention',
                'schema_version' => 1,
                'metadata' => [
                    'quota_event' => 'AD_DELIVERED',
                    'value_event' => 'AD_QUALIFIED_ATTENTION',
                ],
            ],
        );

        $hasActiveRule = $event->rules()
            ->where('status', 'active')
            ->where('currency', 'XOF')
            ->exists();

        if ($hasActiveRule) {
            return;
        }

        $userShare = (int) config('value-engine.user_share_basis_points');
        $wasplexShare = (int) config('value-engine.wasplex_share_basis_points');

        if ($userShare + $wasplexShare !== 10000) {
            throw new ValueEngineException('La configuration initiale du partage publicitaire doit totaliser 100 %.');
        }

        $ruleCode = (string) config('value-engine.advertising_rule_code');
        $nextVersion = (int) ValueRuleVersion::query()->where('code', $ruleCode)->max('version') + 1;

        ValueRuleVersion::query()->create([
            'value_event_definition_id' => $event->id,
            'code' => $ruleCode,
            'version' => $nextVersion,
            'status' => 'active',
            'country_code' => null,
            'currency' => 'XOF',
            'economic_class' => null,
            'user_share_basis_points' => $userShare,
            'wasplex_share_basis_points' => $wasplexShare,
            'minimum_event_amount_minor' => (int) config('value-engine.minimum_event_amount_minor'),
            'maximum_event_amount_minor' => null,
            'required_attention_ms' => (int) config('value-engine.required_attention_ms'),
            'heartbeat_interval_ms' => (int) config('value-engine.heartbeat_interval_ms'),
            'attempt_ttl_seconds' => (int) config('value-engine.attempt_ttl_seconds'),
            'effective_from' => now(),
            'effective_until' => null,
            'calculation_parameters' => [
                'source' => 'campaign_quote',
                'distribution' => 'per_estimated_impression',
                'rounding' => 'floor_with_remainder_preserved',
            ],
            'published_at' => now(),
        ]);
    }

    public function advertisingEvent(): ValueEventDefinition
    {
        $event = ValueEventDefinition::query()
            ->where('code', (string) config('value-engine.advertising_event_code'))
            ->where('status', 'active')
            ->first();

        if (! $event instanceof ValueEventDefinition) {
            throw new ValueEngineException('L’événement publicitaire valorisable n’est pas actif.');
        }

        return $event;
    }

    public function resolveAdvertisingRule(
        string $economicClass,
        string $countryCode,
        string $currency,
    ): ValueRuleVersion {
        $event = $this->advertisingEvent();
        $now = now();

        $rule = ValueRuleVersion::query()
            ->where('value_event_definition_id', $event->id)
            ->where('status', 'active')
            ->where('currency', strtoupper($currency))
            ->where(function ($query) use ($countryCode): void {
                $query->whereNull('country_code')
                    ->orWhere('country_code', strtoupper($countryCode));
            })
            ->where(function ($query) use ($economicClass): void {
                $query->whereNull('economic_class')
                    ->orWhere('economic_class', strtoupper($economicClass));
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $now);
            })
            ->get()
            ->sortByDesc(static fn (ValueRuleVersion $candidate): int => ($candidate->country_code !== null ? 2 : 0)
                + ($candidate->economic_class !== null ? 1 : 0))
            ->first();

        if (! $rule instanceof ValueRuleVersion) {
            throw new ValueEngineException('Aucune règle publicitaire active ne correspond à ce marché.');
        }

        return $rule;
    }

    public function revenueAccountId(): string
    {
        return $this->ledger->openAccount(
            typeCode: 'REVENUE',
            code: 'wasplex.revenue.advertising.wp',
            unit: 'WP',
            currency: 'XOF',
            ownerType: 'platform',
            ownerId: 'wasplex',
            countryCode: null,
            metadata: ['purpose' => 'advertising_revenue'],
        )->id;
    }
}
