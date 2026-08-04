<?php

namespace App\Modules\Advertising\Console\Commands;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingPurpose;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BootstrapAdvertising extends Command
{
    protected $signature = 'advertising:bootstrap';

    protected $description = 'Initialise P008 : finalités, taxonomies, questions et capacités.';

    public function handle(CapabilityService $capabilities): int
    {
        DB::transaction(function (): void {
            $this->seedPurposes();
            $this->seedQuestions();
        });

        $spaces = 0;

        UserSpace::query()
            ->with('account')
            ->whereIn('kind', [
                SpaceKind::User->value,
                SpaceKind::Advertiser->value,
                SpaceKind::Administration->value,
            ])
            ->each(function (UserSpace $space) use ($capabilities, &$spaces): void {
                foreach ($this->capabilitiesFor($space->kind) as $capability) {
                    $exists = $space->account->capabilityGrants()
                        ->where('space_id', $space->id)
                        ->where('capability', $capability)
                        ->whereNull('revoked_at')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $capabilities->grant(
                        account: $space->account,
                        capability: $capability,
                        space: $space,
                        organizationId: $space->organization_id,
                        reason: 'Initialisation P008 SmartProfile, consentements et Matching',
                    );
                }

                $spaces++;
            });

        $this->info('P008 initialisé : finalités et questions publicitaires publiées.');
        $this->info("Capacités P008 vérifiées pour {$spaces} espace(s).");
        $this->warn('Santé, Alertes, Fonds, KYC et autres données sensibles restent interdites au ciblage.');

        return self::SUCCESS;
    }

    private function seedPurposes(): void
    {
        $definitions = [
            'advertising_personalization' => [
                'title' => 'Personnalisation publicitaire',
                'description' => 'Autorise Wasplex à sélectionner des publicités plus pertinentes à partir de critères autorisés.',
                'refusal' => 'Vous continuerez à utiliser Wasplex, mais aucune publicité personnalisée ne sera sélectionnée pour vous.',
            ],
            'smart_profile_usage' => [
                'title' => 'Utilisation du profil intelligent',
                'description' => 'Autorise l’utilisation de vos réponses volontaires pour le Matching publicitaire protégé.',
                'refusal' => 'Vos réponses restent dans votre espace mais ne seront plus utilisées pour de nouveaux matchings.',
            ],
            'approximate_location_targeting' => [
                'title' => 'Zone approximative',
                'description' => 'Autorise l’utilisation d’une zone déclarée approximative, jamais d’une position intime précise.',
                'refusal' => 'Les campagnes dépendant d’une commune ou d’une zone ne pourront pas être sélectionnées pour vous.',
            ],
        ];

        foreach ($definitions as $code => $definition) {
            $purpose = AdvertisingPurpose::query()->firstOrCreate(
                ['code' => $code],
                ['domain' => 'advertising', 'status' => 'active'],
            );

            if ($purpose->current_version_id !== null) {
                continue;
            }

            $version = $purpose->versions()->create([
                'version' => 1,
                'state' => 'published',
                'title' => $definition['title'],
                'description' => $definition['description'],
                'refusal_consequence' => $definition['refusal'],
                'consent_required' => true,
                'requires_new_decision' => true,
                'effective_from' => now(),
                'published_at' => now(),
            ]);

            $purpose->forceFill(['current_version_id' => $version->id])->save();
        }
    }

    private function seedQuestions(): void
    {
        $definitions = [
            [
                'taxonomy' => [
                    'code' => 'telecom.network.primary',
                    'category' => 'usage',
                    'label' => 'Réseau télécom principal',
                ],
                'question' => [
                    'code' => 'primary_telecom_network',
                    'sort' => 10,
                    'prompt' => 'Quel réseau utilisez-vous principalement ?',
                    'help' => 'Cette réponse aide Wasplex à éviter les offres sans rapport avec votre usage déclaré.',
                    'privacy' => 'La réponse est facultative et ne sera jamais remise nominativement à un annonceur.',
                    'purposes' => ['advertising_personalization', 'smart_profile_usage'],
                    'freshness' => 180,
                    'options' => [
                        ['value' => 'orange', 'label' => 'Orange'],
                        ['value' => 'mtn', 'label' => 'MTN'],
                        ['value' => 'moov_africa', 'label' => 'Moov Africa'],
                        ['value' => 'other', 'label' => 'Autre'],
                    ],
                ],
            ],
            [
                'taxonomy' => [
                    'code' => 'interest.mobile_internet',
                    'category' => 'interest',
                    'label' => 'Intérêt pour les offres Internet mobile',
                ],
                'question' => [
                    'code' => 'mobile_internet_interest',
                    'sort' => 20,
                    'prompt' => 'Les offres Internet mobile vous intéressent-elles ?',
                    'help' => 'Cette information permet de distinguer un intérêt déclaré d’un simple usage télécom.',
                    'privacy' => 'La réponse est facultative, modifiable et utilisée uniquement pour une finalité autorisée.',
                    'purposes' => ['advertising_personalization', 'smart_profile_usage'],
                    'freshness' => 365,
                    'options' => [
                        ['value' => 'yes', 'label' => 'Oui'],
                        ['value' => 'no', 'label' => 'Non'],
                        ['value' => 'later', 'label' => 'Peut-être plus tard'],
                    ],
                ],
            ],
            [
                'taxonomy' => [
                    'code' => 'geo.ci.abidjan.commune',
                    'category' => 'territory',
                    'label' => 'Commune approximative à Abidjan',
                ],
                'question' => [
                    'code' => 'abidjan_approximate_commune',
                    'sort' => 30,
                    'prompt' => 'Quelle commune d’Abidjan correspond le mieux à votre zone utile ?',
                    'help' => 'Choisissez une zone approximative de résidence, travail ou intérêt. Aucune position GPS précise n’est requise.',
                    'privacy' => 'Cette réponse nécessite votre consentement distinct à la localisation approximative.',
                    'purposes' => [
                        'advertising_personalization',
                        'smart_profile_usage',
                        'approximate_location_targeting',
                    ],
                    'freshness' => 180,
                    'options' => [
                        ['value' => 'cocody', 'label' => 'Cocody'],
                        ['value' => 'plateau', 'label' => 'Plateau'],
                        ['value' => 'marcory', 'label' => 'Marcory'],
                        ['value' => 'yopougon', 'label' => 'Yopougon'],
                        ['value' => 'other', 'label' => 'Autre commune'],
                    ],
                ],
            ],
            [
                'taxonomy' => [
                    'code' => 'project.smartphone.purchase_intent',
                    'category' => 'project',
                    'label' => 'Projet d’achat de smartphone',
                ],
                'question' => [
                    'code' => 'smartphone_purchase_project',
                    'sort' => 40,
                    'prompt' => 'Prévoyez-vous d’acheter ou de changer de smartphone ?',
                    'help' => 'Un projet déclaré est différent d’une possession ou d’un simple centre d’intérêt.',
                    'privacy' => 'La réponse est facultative et peut être supprimée à tout moment.',
                    'purposes' => ['advertising_personalization', 'smart_profile_usage'],
                    'freshness' => 120,
                    'options' => [
                        ['value' => 'within_3_months', 'label' => 'Dans les 3 mois'],
                        ['value' => 'within_6_months', 'label' => 'Dans les 6 mois'],
                        ['value' => 'later', 'label' => 'Plus tard'],
                        ['value' => 'no', 'label' => 'Non'],
                    ],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $taxonomy = AdvertisingTaxonomy::query()->firstOrCreate(
                ['code' => $definition['taxonomy']['code']],
                [
                    'category' => $definition['taxonomy']['category'],
                    'label' => $definition['taxonomy']['label'],
                    'value_type' => 'single_choice',
                    'allowed_for_targeting' => true,
                    'sensitive' => false,
                    'status' => 'active',
                ],
            );
            $question = AdvertisingProfileQuestion::query()->firstOrCreate(
                ['code' => $definition['question']['code']],
                [
                    'advertising_taxonomy_id' => $taxonomy->id,
                    'status' => 'active',
                    'sort_order' => $definition['question']['sort'],
                ],
            );

            if ($question->current_version_id !== null) {
                continue;
            }

            $version = $question->versions()->create([
                'version' => 1,
                'state' => 'published',
                'prompt' => $definition['question']['prompt'],
                'help_text' => $definition['question']['help'],
                'privacy_note' => $definition['question']['privacy'],
                'options' => $definition['question']['options'],
                'purpose_codes' => $definition['question']['purposes'],
                'optional' => true,
                'freshness_days' => $definition['question']['freshness'],
                'effective_from' => now(),
                'published_at' => now(),
            ]);

            $question->forceFill(['current_version_id' => $version->id])->save();
        }
    }

    /** @return array<int, string> */
    private function capabilitiesFor(SpaceKind $kind): array
    {
        return match ($kind) {
            SpaceKind::User => [
                'advertising.profile.view.self',
                'advertising.profile.manage.self',
                'advertising.consent.view.self',
                'advertising.consent.manage.self',
                'advertising.explanation.view.self',
            ],
            SpaceKind::Advertiser => [
                'advertiser.targeting.taxonomy.view',
                'advertiser.segment.estimate',
            ],
            SpaceKind::Administration => [
                'advertising.configuration.view',
                'advertising.configuration.manage',
                'advertising.configuration.publish',
                'advertising.match.audit.view',
            ],
        };
    }
}
