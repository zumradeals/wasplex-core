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
 * Catalogue économique V1 approuvé :
 * - récompense fixe par vue complète et quota mensuel par niveau ;
 * - prix de lancement des offres commerciales ;
 * - publication explicite des offres payantes par un administrateur.
 *
 * Le partage publicitaire 50/50 reste appliqué au moment de la capture d'une
 * vue complète par le module Campaigns/AdvertiserWallet. Les montants ci-dessous
 * ne sont jamais des pourcentages du budget total de l'annonceur.
 */
final class SeedEconomicCatalogCommand extends Command
{
    protected $signature = 'subscriptions:seed-catalog';

    protected $description = 'Initialise les niveaux membres et les offres commerciales Wasplex (idempotent).';

    /** @var array<int, array{code: string, name: string, quota: int, reward: int, price: int}> */
    private const CLASSES = [
        ['code' => EconomicClass::CODE_FREE, 'name' => 'Gratuit', 'quota' => 120, 'reward' => 30, 'price' => 0],
        ['code' => EconomicClass::CODE_PREMIUM, 'name' => 'Premium', 'quota' => 300, 'reward' => 40, 'price' => 1500],
        ['code' => EconomicClass::CODE_GOLD, 'name' => 'Gold', 'quota' => 600, 'reward' => 50, 'price' => 4000],
        ['code' => EconomicClass::CODE_PLATINUM, 'name' => 'Platine', 'quota' => 900, 'reward' => 60, 'price' => 7500],
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

            $latestVersion = SubscriptionPlanVersion::query()
                ->where('plan_id', $plan->id)
                ->latest('created_at')
                ->first();

            if ($latestVersion === null) {
                $isFree = $definition['code'] === EconomicClass::CODE_FREE;

                $latestVersion = SubscriptionPlanVersion::query()->create([
                    'plan_id' => $plan->id,
                    'status' => $isFree
                        ? SubscriptionPlanVersion::STATUS_PUBLISHED
                        : SubscriptionPlanVersion::STATUS_DRAFT,
                    'price_minor' => $definition['price'],
                    'currency' => 'XOF',
                    'duration_days' => 30,
                    'services_snapshot' => $isFree
                        ? null
                        : ['note' => 'Prix de lancement approuvé — publication manuelle requise.'],
                    'effective_from' => $isFree ? now() : null,
                ]);

                PlanEconomicClassLink::query()->create([
                    'plan_version_id' => $latestVersion->id,
                    'economic_class_id' => $economicClass->id,
                ]);

                continue;
            }

            // Mise à niveau sûre des anciens brouillons créés avant la décision
            // commerciale : un prix déjà modifié par un administrateur n'est
            // jamais écrasé, et aucune offre payante n'est publiée automatiquement.
            if (
                $definition['code'] !== EconomicClass::CODE_FREE
                && $latestVersion->status === SubscriptionPlanVersion::STATUS_DRAFT
                && (int) $latestVersion->price_minor === 0
            ) {
                $latestVersion->update(['price_minor' => $definition['price']]);
            }
        }

        $this->info('Catalogue V1 prêt : Gratuit, Premium, Gold et Platine.');
        $this->info('Prix de lancement : Premium 1 500, Gold 4 000, Platine 7 500 FCFA / 30 jours.');
        $this->warn('Les offres payantes restent en brouillon tant qu’un administrateur ne les publie pas.');

        return self::SUCCESS;
    }
}
