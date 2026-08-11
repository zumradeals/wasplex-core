<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Console;

use App\Modules\SmartProfile\Application\Services\ConsentPurposeService;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Console\Command;

/**
 * Starter catalogue only. The member experience is driven by explicit facet
 * and input metadata so that mutually-exclusive choices are not rendered as
 * unrelated yes/no questions.
 */
final class SeedCatalogCommand extends Command
{
    protected $signature = 'smartprofile:seed-catalog';

    protected $description = 'Initialise un catalogue de départ de taxonomies et de finalités de consentement (idempotent).';

    private const TAXONOMIES = [
        ['code' => 'demographic.gender.woman', 'category' => 'demographic', 'facet' => 'demographic.gender', 'input_type' => 'single_choice', 'label' => 'Femme'],
        ['code' => 'demographic.gender.man', 'category' => 'demographic', 'facet' => 'demographic.gender', 'input_type' => 'single_choice', 'label' => 'Homme'],
        ['code' => 'demographic.age.18_24', 'category' => 'demographic', 'facet' => 'demographic.age_band', 'input_type' => 'single_choice', 'label' => '18–24 ans'],
        ['code' => 'demographic.age.25_34', 'category' => 'demographic', 'facet' => 'demographic.age_band', 'input_type' => 'single_choice', 'label' => '25–34 ans'],
        ['code' => 'demographic.age.35_44', 'category' => 'demographic', 'facet' => 'demographic.age_band', 'input_type' => 'single_choice', 'label' => '35–44 ans'],
        ['code' => 'demographic.age.45_54', 'category' => 'demographic', 'facet' => 'demographic.age_band', 'input_type' => 'single_choice', 'label' => '45–54 ans'],
        ['code' => 'demographic.age.55_plus', 'category' => 'demographic', 'facet' => 'demographic.age_band', 'input_type' => 'single_choice', 'label' => '55 ans et plus'],
        ['code' => 'possession.smartphone', 'category' => 'possession', 'facet' => 'possession.smartphone', 'input_type' => 'boolean', 'label' => 'Possède un smartphone'],
        ['code' => 'possession.deux_roues', 'category' => 'possession', 'facet' => 'possession.deux_roues', 'input_type' => 'boolean', 'label' => 'Possède un deux-roues'],
        ['code' => 'possession.voiture', 'category' => 'possession', 'facet' => 'possession.voiture', 'input_type' => 'boolean', 'label' => 'Possède une voiture'],
        ['code' => 'usage.transport_public', 'category' => 'usage', 'facet' => 'usage.transport_public', 'input_type' => 'boolean', 'label' => 'Utilise les transports en commun'],
        ['code' => 'interest.internet_mobile', 'category' => 'interest', 'facet' => 'interest', 'input_type' => 'multi_choice', 'label' => 'Internet mobile'],
        ['code' => 'interest.mode_beaute', 'category' => 'interest', 'facet' => 'interest', 'input_type' => 'multi_choice', 'label' => 'Mode et beauté'],
        ['code' => 'interest.formation', 'category' => 'interest', 'facet' => 'interest', 'input_type' => 'multi_choice', 'label' => 'Formation et compétences'],
        ['code' => 'project.achat_vehicule', 'category' => 'project', 'facet' => 'project', 'input_type' => 'multi_choice', 'label' => 'Acheter un véhicule'],
        ['code' => 'project.creation_entreprise', 'category' => 'project', 'facet' => 'project', 'input_type' => 'multi_choice', 'label' => 'Créer une entreprise'],
        ['code' => 'situation.etudiant', 'category' => 'situation', 'facet' => 'situation', 'input_type' => 'multi_choice', 'label' => 'Étudiant(e)'],
        ['code' => 'situation.entrepreneur', 'category' => 'situation', 'facet' => 'situation', 'input_type' => 'multi_choice', 'label' => 'Entrepreneur(e)'],
        ['code' => 'territory.ci.abidjan.cocody', 'category' => 'territory', 'facet' => 'territory.approximate_area', 'input_type' => 'single_choice', 'label' => 'Abidjan, Cocody'],
        ['code' => 'territory.ci.abidjan.yopougon', 'category' => 'territory', 'facet' => 'territory.approximate_area', 'input_type' => 'single_choice', 'label' => 'Abidjan, Yopougon'],
    ];

    /**
     * Country-specific mobile operators must not be part of the international
     * default catalogue. They can return later through a country-aware
     * operator catalogue instead of hard-coded global assumptions.
     */
    private const LEGACY_OPERATOR_CODES = [
        'usage.reseau_orange',
        'usage.reseau_mtn',
    ];

    private const CONSENT_PURPOSES = [
        [
            'code' => ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION,
            'name' => 'Personnalisation publicitaire',
            'description' => 'Permet à Wasplex de vous proposer des campagnes en fonction de votre pays et des informations que vous choisissez de partager. Sans cet accord, vous ne recevez aucune publicité personnalisée.',
        ],
        [
            'code' => ConsentPurpose::CODE_SMART_PROFILE_USAGE,
            'name' => 'Utilisation du profil intelligent',
            'description' => 'Permet d\'utiliser les informations que vous déclarez dans votre profil intelligent pour affiner les campagnes qui vous sont proposées.',
        ],
        [
            'code' => ConsentPurpose::CODE_APPROXIMATE_LOCATION_TARGETING,
            'name' => 'Ciblage par zone approximative',
            'description' => "Permet d'utiliser une zone approximative (ville, commune) que vous déclarez vous-même pour des campagnes locales. Wasplex n'utilise jamais votre position GPS exacte.",
        ],
    ];

    public function handle(ConsentPurposeService $purposes): int
    {
        foreach (self::TAXONOMIES as $data) {
            $taxonomy = ProfileTaxonomy::query()->firstOrCreate(
                ['code' => $data['code']],
                [
                    'category' => $data['category'],
                    'facet' => $data['facet'],
                    'input_type' => $data['input_type'],
                    'label' => $data['label'],
                    'status' => ProfileTaxonomy::STATUS_ACTIVE,
                ],
            );

            $taxonomy->forceFill([
                'category' => $data['category'],
                'facet' => $data['facet'],
                'input_type' => $data['input_type'],
                'label' => $data['label'],
            ])->save();
        }

        ProfileTaxonomy::query()
            ->whereIn('code', self::LEGACY_OPERATOR_CODES)
            ->update(['status' => ProfileTaxonomy::STATUS_SUSPENDED]);

        foreach (self::CONSENT_PURPOSES as $data) {
            $purpose = ConsentPurpose::query()->where('code', $data['code'])->first();

            if ($purpose !== null && $purpose->publishedVersion() !== null) {
                continue;
            }

            $version = $purposes->createDraftVersion($data);
            $purposes->publish($version->id);
        }

        $this->info('Catalogue SmartProfile initialisé : '.count(self::TAXONOMIES).' taxonomies, '.count(self::CONSENT_PURPOSES).' finalités de consentement.');
        $this->warn('Les opérateurs mobiles codés en dur sont suspendus : un catalogue opérateur devra être contextualisé par pays.');

        return self::SUCCESS;
    }
}
