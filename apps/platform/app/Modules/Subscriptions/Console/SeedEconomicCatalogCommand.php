<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Console;

use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Subscriptions\Infrastructure\Models\PlanEconomicClassLink;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Console\Command;

/**
 * Docs/03-abonnements-et-classes-economiques-wasplex.md §28 ("décision
 * finale") gives exact, non-negotiable quota/weight values — safe to seed
 * as data. §13's coefficients are explicitly "illustratif" but are the
 * only concrete numbers in the corpus, seeded as the starting point of an
 * administrable, versioned value (never hardcoded in matching logic,
 * which doesn't exist yet anyway).
 *
 * Deliberately NOT seeded: real commercial prices for Premium/Gold/Platine.
 * No FCFA amount for any paid plan exists anywhere in docs/ — inventing one
 * would be a silent product decision (CLAUDE.md §2). Paid plans are seeded
 * as a single `draft` version at price 0, structurally complete but never
 * auto-published; a founder/admin must set the real price via
 * PATCH /api/admin/subscriptions/plans/{id} before publishing. The Gratuit
 * plan is the only one seeded `published`, since 0 is its actual price.
 */
final class SeedEconomicCatalogCommand extends Command
{
    protected $signature = 'subscriptions:seed-catalog';

    protected $description = 'Initialise les classes économiques et les plans commerciaux (idempotent).';

    /** @var array<int, array{code: string, name: string, quota: int, reward: int}> */
    private const CLASSES = [
        ['code' => EconomicClass::CODE_FREE, 'name' => 'Gratuit', 'quota' => 120, 'reward' => 30],
        ['code' => EconomicClass::CODE_PREMIUM, 'name' => 'Premium', 'quota' => 300, 'reward' => 40],
        ['code' => EconomicClass::CODE_GOLD, 'name' => 'Gold', 'quota' => 600, 'reward' => 50],
        ['code' => EconomicClass::CODE_PLATINUM, 'name' => 'Platine', 'quota' => 900, 'reward' => 60],
    ];

    public function handle(): int
    {
        foreach (self::CLASSES as $definition) {
            $economicClass = EconomicClass::query()->firstOrCreate(
                ['code' => $definition['code']],
                ['status' => 'active'],
            );

            $hasCurrentVersion = EconomicClassVersion::query()
                ->where('economic_class_id', $economicClass->id)
                ->whereNull('effective_to')
                ->exists();

            if (! $hasCurrentVersion) {
                EconomicClassVersion::query()->create([
                    'economic_class_id' => $economicClass->id,
                    'quota_monthly' => $definition['quota'],
                    // Colonnes historiques conservées le temps d'une migration
                    // destructive ultérieure ; elles ne participent plus à aucun calcul.
                    'weight_percent' => '0.00',
                    'coefficient' => '1.0000',
                    'reward_per_complete_view_minor' => $definition['reward'],
                    'currency' => 'XOF',
                    'effective_from' => now(),
                ]);
            }

            $plan = SubscriptionPlan::query()->firstOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name']],
            );

            $hasVersion = SubscriptionPlanVersion::query()->where('plan_id', $plan->id)->exists();

            if (! $hasVersion) {
                $isFree = $definition['code'] === EconomicClass::CODE_FREE;

                $version = SubscriptionPlanVersion::query()->create([
                    'plan_id' => $plan->id,
                    'status' => $isFree ? SubscriptionPlanVersion::STATUS_PUBLISHED : SubscriptionPlanVersion::STATUS_DRAFT,
                    'price_minor' => 0,
                    'currency' => 'XOF',
                    'duration_days' => 30,
                    'services_snapshot' => $isFree ? null : ['note' => 'Prix à définir par le fondateur avant publication.'],
                    'effective_from' => $isFree ? now() : null,
                ]);

                PlanEconomicClassLink::query()->create([
                    'plan_version_id' => $version->id,
                    'economic_class_id' => $economicClass->id,
                ]);
            }
        }

        $this->info('Classes économiques et plans initialisés : '.implode(', ', array_column(self::CLASSES, 'code')));
        $this->warn('Premium/Gold/Platine restent en brouillon (prix à définir) — voir docs/chantiers/P004-RAPPORT.md.');

        return self::SUCCESS;
    }
}
