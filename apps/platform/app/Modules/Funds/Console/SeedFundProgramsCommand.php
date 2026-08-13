<?php

declare(strict_types=1);

namespace App\Modules\Funds\Console;

use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use Illuminate\Console\Command;

final class SeedFundProgramsCommand extends Command
{
    protected $signature = 'funds:seed-programs';

    protected $description = 'Initialise les 3 programmes Fonds et les catégories de lancement (idempotent).';

    /** @var array<int, array<string, mixed>> */
    private const PROGRAMS = [
        [
            'code' => 'fonds-essentiel',
            'name' => 'Fonds Essentiel',
            'sort_order' => 10,
            'membership_fee_minor' => 1000,
            'duration_days' => 365,
            'max_active_wishes' => 1,
            'max_wishes_per_period' => 2,
            'max_wish_amount_minor' => 150000,
            'personal_contribution_percent' => 30,
            'min_debit_minor' => 100,
            'max_debit_minor' => 500,
            'daily_cap_minor' => 1000,
            'monthly_cap_minor' => 3000,
            'annual_cap_minor' => 36000,
            'wasplex_fee_minor' => 50,
            'notice_hours' => 24,
            'grace_period_days' => 7,
            'arrears_grace_days' => 7,
            'max_simultaneous_collections' => 1,
            'emergency_queue_share_percent' => 20,
            'reserve_min_balance_minor' => 0,
            'reciprocity_min_score' => 0,
            'rehabilitation_incident_threshold' => 3,
            'eligible_subscription_classes' => ['PREMIUM', 'GOLD', 'PLATINUM'],
        ],
        [
            'code' => 'fonds-plus',
            'name' => 'Fonds Plus',
            'sort_order' => 20,
            'membership_fee_minor' => 2500,
            'duration_days' => 365,
            'max_active_wishes' => 2,
            'max_wishes_per_period' => 3,
            'max_wish_amount_minor' => 500000,
            'personal_contribution_percent' => 25,
            'min_debit_minor' => 250,
            'max_debit_minor' => 1000,
            'daily_cap_minor' => 2000,
            'monthly_cap_minor' => 7500,
            'annual_cap_minor' => 90000,
            'wasplex_fee_minor' => 100,
            'notice_hours' => 24,
            'grace_period_days' => 7,
            'arrears_grace_days' => 7,
            'max_simultaneous_collections' => 2,
            'emergency_queue_share_percent' => 20,
            'reserve_min_balance_minor' => 0,
            'reciprocity_min_score' => 10,
            'rehabilitation_incident_threshold' => 3,
            'eligible_subscription_classes' => ['GOLD', 'PLATINUM'],
        ],
        [
            'code' => 'fonds-excellence',
            'name' => 'Fonds Excellence',
            'sort_order' => 30,
            'membership_fee_minor' => 5000,
            'duration_days' => 365,
            'max_active_wishes' => 2,
            'max_wishes_per_period' => 4,
            'max_wish_amount_minor' => 1000000,
            'personal_contribution_percent' => 20,
            'min_debit_minor' => 500,
            'max_debit_minor' => 2000,
            'daily_cap_minor' => 4000,
            'monthly_cap_minor' => 15000,
            'annual_cap_minor' => 180000,
            'wasplex_fee_minor' => 150,
            'notice_hours' => 48,
            'grace_period_days' => 10,
            'arrears_grace_days' => 10,
            'max_simultaneous_collections' => 2,
            'emergency_queue_share_percent' => 20,
            'reserve_min_balance_minor' => 0,
            'reciprocity_min_score' => 20,
            'rehabilitation_incident_threshold' => 3,
            'eligible_subscription_classes' => ['PLATINUM'],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const CATEGORIES = [
        ['code' => 'sante-soins', 'name' => 'Santé & soins', 'icon' => '❤', 'description' => 'Soins, médicaments, examens et interventions médicales vérifiées.', 'sensitive' => true],
        ['code' => 'logement-habitat', 'name' => 'Logement & habitat', 'icon' => '⌂', 'description' => 'Logement essentiel, rénovation et amélioration de l’habitat.', 'sensitive' => false],
        ['code' => 'mobilite-transport', 'name' => 'Mobilité & transport', 'icon' => '↗', 'description' => 'Moto, tricycle, taxi ou véhicule nécessaire à une activité.', 'sensitive' => false],
        ['code' => 'formation-scolarite', 'name' => 'Formation & scolarité', 'icon' => '✦', 'description' => 'Formation, scolarité et équipement éducatif vérifiable.', 'sensitive' => false],
        ['code' => 'equipement-professionnel', 'name' => 'Équipement professionnel', 'icon' => '⚙', 'description' => 'Outils et équipements nécessaires à une activité professionnelle.', 'sensitive' => false],
        ['code' => 'agriculture-production', 'name' => 'Agriculture & production', 'icon' => '♧', 'description' => 'Équipement agricole et moyens de production.', 'sensitive' => false],
        ['code' => 'eau-energie', 'name' => 'Eau & énergie', 'icon' => '◇', 'description' => 'Accès à l’eau, électrification et solutions énergétiques essentielles.', 'sensitive' => false],
        ['code' => 'creation-activite', 'name' => 'Création / relance d’activité', 'icon' => '＋', 'description' => 'Création ou relance documentée d’une activité économique.', 'sensitive' => false],
        ['code' => 'accessibilite-handicap', 'name' => 'Accessibilité & handicap', 'icon' => '◎', 'description' => 'Équipements et adaptations liés à l’accessibilité.', 'sensitive' => true],
        ['code' => 'urgence-familiale', 'name' => 'Urgence familiale grave', 'icon' => '!', 'description' => 'Besoin familial grave vérifié pouvant relever de la file urgence.', 'sensitive' => true],
    ];

    public function handle(): int
    {
        foreach (self::PROGRAMS as $definition) {
            $program = FundProgram::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'status' => FundProgram::STATUS_ACTIVE,
                    'sort_order' => $definition['sort_order'],
                ],
            );

            if (! $program->versions()->exists()) {
                FundProgramVersion::query()->create([
                    'fund_program_id' => $program->id,
                    'version' => 1,
                    'currency' => 'XOF',
                    'membership_fee_minor' => $definition['membership_fee_minor'],
                    'duration_days' => $definition['duration_days'],
                    'minimum_subscription_age_days' => 0,
                    'max_active_wishes' => $definition['max_active_wishes'],
                    'max_wishes_per_period' => $definition['max_wishes_per_period'],
                    'max_wish_amount_minor' => $definition['max_wish_amount_minor'],
                    'personal_contribution_percent' => $definition['personal_contribution_percent'],
                    'min_debit_minor' => $definition['min_debit_minor'],
                    'max_debit_minor' => $definition['max_debit_minor'],
                    'daily_cap_minor' => $definition['daily_cap_minor'],
                    'monthly_cap_minor' => $definition['monthly_cap_minor'],
                    'annual_cap_minor' => $definition['annual_cap_minor'],
                    'wasplex_fee_minor' => $definition['wasplex_fee_minor'],
                    'notice_hours' => $definition['notice_hours'],
                    'grace_period_days' => $definition['grace_period_days'],
                    'arrears_grace_days' => $definition['arrears_grace_days'],
                    'max_simultaneous_collections' => $definition['max_simultaneous_collections'],
                    'emergency_queue_share_percent' => $definition['emergency_queue_share_percent'],
                    'reserve_min_balance_minor' => $definition['reserve_min_balance_minor'],
                    'reciprocity_min_score' => $definition['reciprocity_min_score'],
                    'rehabilitation_incident_threshold' => $definition['rehabilitation_incident_threshold'],
                    'eligible_subscription_classes' => $definition['eligible_subscription_classes'],
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }
        }

        foreach (self::CATEGORIES as $index => $definition) {
            FundWishCategory::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'requires_sensitive_documents' => $definition['sensitive'],
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }

        $this->info('Fonds prêt : Essentiel, Plus et Excellence.');
        $this->info('Adhésions annuelles : 1 000 / 2 500 / 5 000 FCFA.');
        $this->info('10 catégories de vœux initialisées.');
        $this->warn('Une relance du seed ne remplace jamais les paramètres déjà modifiés par l’administration.');

        return self::SUCCESS;
    }
}
