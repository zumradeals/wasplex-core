<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Console;

use App\Modules\SmartProfile\Application\Services\ConsentPurposeService;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Console\Command;

/**
 * Contenu de départ, explicitement ajustable par l'administration — pas
 * une décision produit figée (docs/chantiers/P008-CHANTIER.md). Les
 * taxonomies couvrent délibérément plusieurs secteurs (télécom, mobilité,
 * mode, éducation, entrepreneuriat, géographie) : leçon retenue de
 * `P008-R-REFONTE-PROFIL-INTELLIGENT.md` §1, qui identifie un catalogue
 * mono-secteur comme une erreur de conception à ne pas reproduire.
 */
final class SeedCatalogCommand extends Command
{
    protected $signature = 'smartprofile:seed-catalog';

    protected $description = 'Initialise un catalogue de départ de taxonomies et de finalités de consentement (idempotent).';

    private const TAXONOMIES = [
        ['code' => 'demographic.gender.woman', 'category' => 'demographic', 'label' => 'Genre déclaré : femme'],
        ['code' => 'demographic.gender.man', 'category' => 'demographic', 'label' => 'Genre déclaré : homme'],
        ['code' => 'possession.smartphone', 'category' => 'possession', 'label' => 'Possède un smartphone'],
        ['code' => 'possession.deux_roues', 'category' => 'possession', 'label' => 'Possède un deux-roues'],
        ['code' => 'usage.reseau_orange', 'category' => 'usage', 'label' => 'Utilise principalement le réseau Orange'],
        ['code' => 'usage.reseau_mtn', 'category' => 'usage', 'label' => 'Utilise principalement le réseau MTN'],
        ['code' => 'usage.transport_public', 'category' => 'usage', 'label' => 'Utilise les transports en commun'],
        ['code' => 'interest.internet_mobile', 'category' => 'interest', 'label' => 'Intéressé par les offres Internet mobile'],
        ['code' => 'interest.mode_beaute', 'category' => 'interest', 'label' => 'Intéressé par la mode et la beauté'],
        ['code' => 'interest.formation', 'category' => 'interest', 'label' => 'Intéressé par la formation et les compétences'],
        ['code' => 'project.achat_vehicule', 'category' => 'project', 'label' => 'Projette d\'acheter un véhicule'],
        ['code' => 'project.creation_entreprise', 'category' => 'project', 'label' => 'Projette de créer une entreprise'],
        ['code' => 'situation.etudiant', 'category' => 'situation', 'label' => 'Situation actuelle : étudiant'],
        ['code' => 'situation.entrepreneur', 'category' => 'situation', 'label' => 'Situation actuelle : entrepreneur'],
        ['code' => 'territory.ci.abidjan.cocody', 'category' => 'territory', 'label' => 'Zone approximative : Abidjan, Cocody'],
        ['code' => 'territory.ci.abidjan.yopougon', 'category' => 'territory', 'label' => 'Zone approximative : Abidjan, Yopougon'],
    ];

    private const CONSENT_PURPOSES = [
        [
            'code' => ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION,
            'name' => 'Personnalisation publicitaire',
            'description' => 'Permet à Wasplex de vous proposer des campagnes en fonction de votre classe, de votre pays et des informations que vous choisissez de partager. Sans cet accord, vous ne recevez aucune publicité personnalisée.',
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
            ProfileTaxonomy::query()->firstOrCreate(
                ['code' => $data['code']],
                ['category' => $data['category'], 'label' => $data['label'], 'status' => ProfileTaxonomy::STATUS_ACTIVE],
            );
        }

        foreach (self::CONSENT_PURPOSES as $data) {
            $purpose = ConsentPurpose::query()->where('code', $data['code'])->first();

            if ($purpose !== null && $purpose->publishedVersion() !== null) {
                continue;
            }

            $version = $purposes->createDraftVersion($data);
            $purposes->publish($version->id);
        }

        $this->info('Catalogue SmartProfile initialisé : '.count(self::TAXONOMIES).' taxonomies, '.count(self::CONSENT_PURPOSES).' finalités de consentement.');
        $this->warn('Contenu de départ ajustable par l\'administration — pas une décision produit figée.');

        return self::SUCCESS;
    }
}
