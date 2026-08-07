<?php

declare(strict_types=1);

/**
 * P013 — jeu de données GamaDeals/Orange (docs/chantiers/P013-CHANTIER.md).
 *
 * Chaque étape appelle le vrai service applicatif du module concerné (la
 * même couche que les contrôleurs HTTP), jamais un raccourci qui écrirait
 * directement dans une table d'un autre module (CLAUDE.md §6). Deux
 * substitutions documentées et assumées, imposées par cet environnement
 * bac à sable :
 *
 *   - dépôt annonceur : flux "dépôt supervisé" (docs/13 §28) au lieu du
 *     flux GeniusPay checkout, car ce bac à sable n'a aucun accès réseau
 *     sortant vers geniuspay.ci (confirmé pendant P010-B/P011-B) ;
 *   - abonnement Gold du candidat : seedé directement via UserSubscription
 *     (comme p011-seed.php avant lui), car seul le plan FREE est publié
 *     par défaut (SeedEconomicCatalogCommand) — publier une vraie
 *     tarification Gold est une décision produit du fondateur, hors
 *     mandat de stabilisation de P013.
 *
 * "Orange" (docs/04 §13, exemple Cocody) est une couleur narrative
 * déclarée côté SmartProfile (taxonomie usage.reseau_orange) : ce n'est
 * pas un axe de ciblage Matching réel dans ce dépôt (seuls
 * economic_classes et territory.country_code le sont — P008-RAPPORT.md,
 * limite déjà documentée). Le Matching réel de ce jeu de données reste
 * donc Gold + CI.
 */

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeLibraryService;
use App\Modules\AdvertiserWallet\Application\Services\SupervisedDepositService;
use App\Modules\Campaigns\Application\Services\CampaignReviewService;
use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewCase;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeModerationCase;
use App\Modules\Identity\Application\Services\AccountRegistrationService;
use App\Modules\Identity\Application\Services\OrganizationRegistrationService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\SmartProfile\Application\Services\ConsentService;
use App\Modules\SmartProfile\Application\Services\ProfileAnswerService;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Http\UploadedFile;

function seedSubscribeToClass(string $accountId, string $classCode): void
{
    $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($q) => $q->where('code', $classCode))
        ->firstOrFail();

    UserSubscription::query()->create([
        'account_id' => $accountId,
        'plan_version_id' => $planVersion->id,
        'economic_class_id' => $economicClass->id,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
}

config(['campaigns.minimum_segment_size' => 2]);

$registration = app(AccountRegistrationService::class);

// Deux comptes Gold supplémentaires pour dépasser le seuil minimal de
// segment (le candidat P013 doit être livré, pas seulement éligible).
foreach (range(1, 2) as $i) {
    $filler = Account::query()->create([
        'status' => 'verified',
        'password' => bcrypt('Password123!'),
        'country_code' => 'CI',
        'language' => 'fr',
        'timezone' => 'UTC',
    ]);
    seedSubscribeToClass($filler->id, 'GOLD');
}

$priceVersion = AdvertisingPriceVersion::query()->where('status', AdvertisingPriceVersion::STATUS_DRAFT)->first();
$priceVersion?->update([
    'base_price_minor_per_event' => 500,
    'status' => AdvertisingPriceVersion::STATUS_PUBLISHED,
    'effective_from' => now(),
    'published_at' => now(),
]);

$founder = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'fondateur@wasplex.test'))->firstOrFail();

// Second administrateur, distinct du fondateur, pour la règle des deux
// personnes du dépôt supervisé (docs/13 §28 : proposeur != approbateur).
$approver = $registration->register('email', 'p013-demo-approver@wasplex.test', 'Password123!', 'CI');
CapabilityGrant::query()->create([
    'account_id' => $approver->id,
    'capability_code' => 'admin.advertiser-wallet.supervised-deposit',
    'status' => 'active',
    'starts_at' => now(),
    'granted_by' => $founder->id,
]);

// --- Annonceur : GamaDeals CI ------------------------------------------------

$advertiser = $registration->register('email', 'p013-demo-advertiser@wasplex.test', 'Password123!', 'CI');
$org = app(OrganizationRegistrationService::class)->register($advertiser, 'GamaDeals CI', 'advertiser', 'CI');

$brand = app(BrandService::class)->create($org->id, ['name' => 'GamaDeals']);

// Créatif vidéo réel, modéré par un vrai admin (P010-B avait court-circuité
// cette étape en créant l'asset directement à l'état "approved" — P013
// exerce le vrai flux upload + décision de modération). Le fichier n'est
// pas versionné (aucun contenu créatif réel n'appartient au dépôt) : fournir
// son chemin via P013_CREATIVE_PATH (n'importe quel mp4/webm court suffit).
$videoPath = getenv('P013_CREATIVE_PATH') ?: throw new RuntimeException(
    'Définir P013_CREATIVE_PATH (chemin absolu vers un fichier .mp4/.webm court) avant d\'exécuter ce script.'
);
$upload = new UploadedFile($videoPath, 'gamadeals-orange-cocody.'.pathinfo($videoPath, PATHINFO_EXTENSION), null, null, true);
$asset = app(CreativeLibraryService::class)->upload($org->id, $brand->id, $upload, $advertiser->id);
$asset = app(CreativeLibraryService::class)->moderate($asset, CreativeModerationCase::DECISION_APPROVE, $founder->id, 'Créatif conforme à la charte GamaDeals.');
echo "asset_id={$asset->id} moderation_status={$asset->moderation_status}\n";

// Dépôt supervisé de 100 000 WP (docs P013 §1) — proposé par le fondateur,
// approuvé par le second administrateur.
$supervised = app(SupervisedDepositService::class);
$deposit = $supervised->create($org->id, 100000, 'WP', 'PREUVE-GAMADEALS-P013-001', 'Dépôt de démonstration P013 — vérifié manuellement.', $founder->id);
$deposit = $supervised->approve($deposit, $approver->id);
echo "deposit_id={$deposit->id} status={$deposit->status}\n";

$campaigns = app(CampaignService::class);
$campaign = $campaigns->create($org->id, $brand->id, $advertiser->id);
$campaigns->updateVersion($org->id, $campaign->id, [
    'objective_code' => 'faire_connaitre',
    'audience_configuration' => ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    'budget_configuration' => ['budget_amount_minor' => 100000],
    'creative_configuration' => ['asset_id' => $asset->id, 'title' => 'GamaDeals x Orange — Cocody', 'duration_seconds' => 5],
]);

$campaigns->quote($org->id, $campaign->id);
$campaigns->fund($org->id, $campaign->id);
$campaigns->submit($org->id, $campaign->id, $advertiser->id);

$caseId = CampaignReviewCase::query()->where('campaign_id', $campaign->id)->orderByDesc('opened_at')->orderByDesc('id')->firstOrFail()->id;
app(CampaignReviewService::class)->approve($caseId, $founder->id);
echo "campaign_id={$campaign->id}\n";

// --- Candidat : Gold, Cocody, réseau Orange (SmartProfile, narratif) -------

$candidate = $registration->register('email', 'p013-demo-candidate@wasplex.test', 'Password123!', 'CI');
seedSubscribeToClass($candidate->id, 'GOLD');

$networkTaxonomy = ProfileTaxonomy::query()->where('code', 'usage.reseau_orange')->firstOrFail();
app(ProfileAnswerService::class)->declare($candidate->id, $networkTaxonomy->id);
app(ConsentService::class)->grant($candidate->id, ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION);

echo "candidate_account_id={$candidate->id}\n";
echo "advertiser_account_id={$advertiser->id}\n";
echo "organization_id={$org->id}\n";
echo "OK\n";
