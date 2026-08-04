<?php

namespace App\Modules\Advertising\Console\Commands;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMarket;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSector;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BootstrapProfileIntelligence extends Command
{
    protected $signature = 'advertising:bootstrap-intelligence';

    protected $description = 'Initialise la refonte internationale du Profil intelligent, avec le Mali comme marché par défaut.';

    public function handle(): int
    {
        $this->call('advertising:bootstrap');

        DB::transaction(function (): void {
            $this->seedMarkets();
            $sectors = $this->seedSectors();
            $this->alignLegacyCatalog($sectors);
            $this->seedStarterCatalog($sectors);
        });

        $this->info('Profil intelligent refondu : le Mali est le marché initial par défaut.');
        $this->info('Secteurs multidimensionnels, taxonomies administrables et signaux IA contrôlés sont initialisés.');
        $this->warn('Les propositions IA restent inactives pour le ciblage tant que l’utilisateur ne les a pas confirmées.');

        return self::SUCCESS;
    }

    private function seedMarkets(): void
    {
        AdvertisingMarket::query()->update(['is_default' => false]);

        AdvertisingMarket::query()->updateOrCreate(
            ['code' => 'ML'],
            [
                'name' => 'Mali',
                'currency' => 'XOF',
                'timezone' => 'Africa/Bamako',
                'default_locale' => 'fr',
                'supported_locales' => ['fr', 'bm'],
                'is_default' => true,
                'status' => 'active',
                'metadata' => [
                    'launch_market' => true,
                    'international_architecture' => true,
                ],
            ],
        );

        AdvertisingMarket::query()->updateOrCreate(
            ['code' => 'CI'],
            [
                'name' => 'Côte d’Ivoire',
                'currency' => 'XOF',
                'timezone' => 'Africa/Abidjan',
                'default_locale' => 'fr',
                'supported_locales' => ['fr'],
                'is_default' => false,
                'status' => 'active',
                'metadata' => ['legacy_catalog_supported' => true],
            ],
        );
    }

    /** @return array<string, AdvertisingSector> */
    private function seedSectors(): array
    {
        $definitions = [
            'general' => ['Profil général', 'Repères généraux, langues, zones et préférences transversales.', 'sparkles'],
            'commerce' => ['Achats & commerce', 'Habitudes d’achat, canaux préférés, gammes et promotions.', 'shopping-bag'],
            'automotive' => ['Automobile & mobilité', 'Véhicules, entretien, location, mobilité et projets d’achat.', 'car'],
            'real-estate' => ['Immobilier & habitat', 'Location, achat, construction, rénovation et équipements du logement.', 'home'],
            'education' => ['Formation & compétences', 'Études, apprentissage, certifications et développement professionnel.', 'graduation-cap'],
            'technology' => ['Technologie', 'Équipements numériques, logiciels, services et usages technologiques.', 'laptop'],
            'telecommunications' => ['Télécommunications', 'Réseaux, connectivité et services de communication.', 'signal'],
            'business' => ['Entreprise & entrepreneuriat', 'Création, gestion, équipements et services professionnels.', 'briefcase'],
            'food' => ['Alimentation & restauration', 'Produits alimentaires, restauration et préférences de découverte.', 'utensils'],
            'fashion-beauty' => ['Mode & beauté', 'Vêtements, accessoires, beauté et soins non sensibles.', 'shirt'],
            'travel' => ['Voyage & loisirs', 'Déplacements, tourisme, hébergement, culture et divertissement.', 'plane'],
            'home-living' => ['Maison & quotidien', 'Équipement domestique, décoration, entretien et besoins du quotidien.', 'sofa'],
            'professional-services' => ['Services professionnels', 'Prestataires, outils métiers et services aux organisations.', 'building'],
        ];
        $result = [];
        $sort = 10;

        foreach ($definitions as $code => [$name, $description, $icon]) {
            $sector = AdvertisingSector::query()->updateOrCreate(
                ['code' => $code],
                [
                    'status' => 'active',
                    'icon' => $icon,
                    'sort_order' => $sort,
                    'allowed_for_targeting' => true,
                    'metadata' => ['starter_catalog' => true],
                ],
            );
            $sector->translations()->updateOrCreate(
                ['locale' => 'fr'],
                ['name' => $name, 'description' => $description],
            );
            $result[$code] = $sector;
            $sort += 10;
        }

        return $result;
    }

    /** @param array<string, AdvertisingSector> $sectors */
    private function alignLegacyCatalog(array $sectors): void
    {
        $assignments = [
            'telecom.network.primary' => 'telecommunications',
            'interest.mobile_internet' => 'telecommunications',
            'geo.ci.abidjan.commune' => 'general',
            'project.smartphone.purchase_intent' => 'technology',
        ];

        foreach ($assignments as $taxonomyCode => $sectorCode) {
            $taxonomy = AdvertisingTaxonomy::query()->where('code', $taxonomyCode)->first();

            if ($taxonomy === null) {
                continue;
            }

            $metadata = $taxonomy->metadata ?? [];
            $metadata['legacy_seed'] = true;
            $metadata['legacy_example_only'] = true;

            $taxonomy->forceFill([
                'advertising_sector_id' => $sectors[$sectorCode]->id,
                'signal_kind' => match ($taxonomy->category) {
                    'usage' => 'habit',
                    'territory' => 'territory',
                    'project' => 'project',
                    default => 'interest',
                },
                'country_codes' => $taxonomyCode === 'geo.ci.abidjan.commune' ? ['CI'] : null,
                'user_visible' => true,
                'ai_usage_mode' => 'assist_only',
                'default_freshness_days' => 180,
                'sort_order' => 900,
                'metadata' => $metadata,
            ])->save();
        }
    }

    /** @param array<string, AdvertisingSector> $sectors */
    private function seedStarterCatalog(array $sectors): void
    {
        $this->seedQuestion(
            sector: $sectors['commerce'],
            taxonomyCode: 'commerce.shopping.channel_preference',
            category: 'preference',
            signalKind: 'preference',
            label: 'Canal d’achat préféré',
            questionCode: 'shopping_channel_preference',
            prompt: 'Comment préférez-vous généralement acheter ?',
            help: 'Cette préférence aide Wasplex à distinguer les offres en ligne, en magasin ou hybrides.',
            options: [
                ['value' => 'online', 'label' => 'En ligne'],
                ['value' => 'store', 'label' => 'En magasin'],
                ['value' => 'both', 'label' => 'Les deux'],
                ['value' => 'depends', 'label' => 'Cela dépend du produit'],
            ],
            freshnessDays: 365,
            sort: 10,
        );

        $this->seedQuestion(
            sector: $sectors['commerce'],
            taxonomyCode: 'commerce.price.positioning_preference',
            category: 'preference',
            signalKind: 'preference',
            label: 'Positionnement de prix recherché',
            questionCode: 'price_positioning_preference',
            prompt: 'Quel type d’offre recherchez-vous le plus souvent ?',
            help: 'Il s’agit d’une préférence générale, pas d’une estimation de vos revenus.',
            options: [
                ['value' => 'economic', 'label' => 'Économique'],
                ['value' => 'balanced', 'label' => 'Bon rapport qualité-prix'],
                ['value' => 'premium', 'label' => 'Premium'],
                ['value' => 'depends', 'label' => 'Selon le besoin'],
            ],
            freshnessDays: 365,
            sort: 20,
        );

        $this->seedQuestion(
            sector: $sectors['automotive'],
            taxonomyCode: 'automotive.current_relationship',
            category: 'status',
            signalKind: 'status',
            label: 'Relation actuelle avec l’automobile',
            questionCode: 'automotive_current_relationship',
            prompt: 'Quelle situation automobile vous correspond actuellement ?',
            help: 'Possession, projet d’achat, entretien, location et simple intérêt sont distingués.',
            options: [
                ['value' => 'owner', 'label' => 'Je possède un véhicule'],
                ['value' => 'purchase_project', 'label' => 'Je souhaite acheter un véhicule'],
                ['value' => 'maintenance_need', 'label' => 'Je recherche surtout de l’entretien'],
                ['value' => 'rental_interest', 'label' => 'Je m’intéresse à la location'],
                ['value' => 'general_interest', 'label' => 'Je consulte simplement ce secteur'],
                ['value' => 'not_interested', 'label' => 'Ce secteur ne m’intéresse pas'],
            ],
            freshnessDays: 180,
            sort: 30,
        );

        $this->seedQuestion(
            sector: $sectors['real-estate'],
            taxonomyCode: 'real_estate.current_project',
            category: 'project',
            signalKind: 'project',
            label: 'Projet immobilier actuel',
            questionCode: 'real_estate_current_project',
            prompt: 'Avez-vous actuellement un projet lié au logement ?',
            help: 'Wasplex distingue la location, l’achat, la construction et la rénovation.',
            options: [
                ['value' => 'rent', 'label' => 'Louer'],
                ['value' => 'buy', 'label' => 'Acheter'],
                ['value' => 'build', 'label' => 'Construire'],
                ['value' => 'renovate', 'label' => 'Rénover'],
                ['value' => 'general_interest', 'label' => 'Intérêt général'],
                ['value' => 'none', 'label' => 'Aucun projet actuel'],
            ],
            freshnessDays: 120,
            sort: 40,
        );

        $this->seedQuestion(
            sector: $sectors['education'],
            taxonomyCode: 'education.training_project',
            category: 'project',
            signalKind: 'project',
            label: 'Projet de formation',
            questionCode: 'education_training_project',
            prompt: 'Envisagez-vous une formation ou une certification ?',
            help: 'Cette information permet de distinguer un projet actif d’un simple intérêt éducatif.',
            options: [
                ['value' => 'now', 'label' => 'Je cherche maintenant'],
                ['value' => 'within_6_months', 'label' => 'Dans les 6 mois'],
                ['value' => 'general_interest', 'label' => 'Je m’informe seulement'],
                ['value' => 'none', 'label' => 'Pas actuellement'],
            ],
            freshnessDays: 180,
            sort: 50,
        );

        $this->seedQuestion(
            sector: $sectors['business'],
            taxonomyCode: 'business.current_need',
            category: 'project',
            signalKind: 'need',
            label: 'Besoin professionnel actuel',
            questionCode: 'business_current_need',
            prompt: 'Quel besoin professionnel vous intéresse actuellement ?',
            help: 'Choisissez uniquement la catégorie la plus utile maintenant.',
            options: [
                ['value' => 'digital_tools', 'label' => 'Outils numériques'],
                ['value' => 'equipment', 'label' => 'Équipements professionnels'],
                ['value' => 'logistics', 'label' => 'Transport et logistique'],
                ['value' => 'business_services', 'label' => 'Services aux entreprises'],
                ['value' => 'none', 'label' => 'Aucun besoin actuel'],
            ],
            freshnessDays: 90,
            sort: 60,
        );

        $this->seedQuestion(
            sector: $sectors['travel'],
            taxonomyCode: 'travel.current_project',
            category: 'project',
            signalKind: 'project',
            label: 'Projet de voyage',
            questionCode: 'travel_current_project',
            prompt: 'Avez-vous un projet de voyage ou de déplacement ?',
            help: 'Cette réponse peut être retirée dès que votre projet est terminé.',
            options: [
                ['value' => 'mali', 'label' => 'À l’intérieur du Mali'],
                ['value' => 'west_africa', 'label' => 'En Afrique de l’Ouest'],
                ['value' => 'international', 'label' => 'À l’international'],
                ['value' => 'general_interest', 'label' => 'Je m’informe seulement'],
                ['value' => 'none', 'label' => 'Pas actuellement'],
            ],
            freshnessDays: 120,
            sort: 70,
        );

        $this->seedQuestion(
            sector: $sectors['home-living'],
            taxonomyCode: 'home.current_project',
            category: 'project',
            signalKind: 'project',
            label: 'Projet pour la maison',
            questionCode: 'home_current_project',
            prompt: 'Avez-vous un projet pour votre maison ou votre quotidien ?',
            help: 'Équipement, décoration, entretien et amélioration sont regroupés sans supposer votre situation personnelle.',
            options: [
                ['value' => 'equipment', 'label' => 'Acheter un équipement'],
                ['value' => 'decoration', 'label' => 'Décoration'],
                ['value' => 'maintenance', 'label' => 'Entretien ou réparation'],
                ['value' => 'improvement', 'label' => 'Amélioration générale'],
                ['value' => 'none', 'label' => 'Pas actuellement'],
            ],
            freshnessDays: 120,
            sort: 80,
        );
    }

    /** @param array<int, array{value:string,label:string}> $options */
    private function seedQuestion(
        AdvertisingSector $sector,
        string $taxonomyCode,
        string $category,
        string $signalKind,
        string $label,
        string $questionCode,
        string $prompt,
        string $help,
        array $options,
        int $freshnessDays,
        int $sort,
    ): void {
        $taxonomy = AdvertisingTaxonomy::query()->updateOrCreate(
            ['code' => $taxonomyCode],
            [
                'advertising_sector_id' => $sector->id,
                'category' => $category,
                'signal_kind' => $signalKind,
                'country_codes' => null,
                'label' => $label,
                'value_type' => 'single_choice',
                'allowed_for_targeting' => true,
                'sensitive' => false,
                'user_visible' => true,
                'ai_usage_mode' => 'proposal_with_confirmation',
                'default_freshness_days' => $freshnessDays,
                'sort_order' => $sort,
                'status' => 'active',
                'metadata' => ['starter_catalog' => true, 'admin_configurable' => true],
            ],
        );
        $question = AdvertisingProfileQuestion::query()->firstOrCreate(
            ['code' => $questionCode],
            [
                'advertising_taxonomy_id' => $taxonomy->id,
                'status' => 'active',
                'sort_order' => $sort,
            ],
        );

        if ($question->current_version_id !== null) {
            return;
        }

        $version = $question->versions()->create([
            'version' => 1,
            'state' => 'published',
            'prompt' => $prompt,
            'help_text' => $help,
            'privacy_note' => 'Réponse facultative, modifiable, expirable et jamais transmise nominativement à un annonceur.',
            'options' => $options,
            'purpose_codes' => ['advertising_personalization', 'smart_profile_usage'],
            'optional' => true,
            'freshness_days' => $freshnessDays,
            'effective_from' => now(),
            'published_at' => now(),
        ]);

        $question->forceFill(['current_version_id' => $version->id])->save();
    }
}
