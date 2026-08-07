<?php

declare(strict_types=1);

use App\Modules\Campaigns\Http\Controllers\Admin\CampaignReviewsController;
use App\Modules\Campaigns\Http\Controllers\Admin\PricingController;
use App\Modules\Campaigns\Http\Controllers\Advertiser\CampaignReportingController;
use App\Modules\Campaigns\Http\Controllers\Advertiser\CampaignsController;
use App\Modules\Campaigns\Http\Controllers\Advertiser\TargetingController;
use App\Modules\Identity\Http\Middleware\EnsureActiveAdvertiserOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

// docs/13-studio-annonceur-wasplex.md §90-91 (sous-ensemble campagne
// rapide) — même middleware que AdvertiserStudio/AdvertiserWallet.
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureActiveAdvertiserOrganization::class])
    ->prefix('advertiser')
    ->group(function (): void {
        Route::get('/economic-classes', [TargetingController::class, 'economicClasses'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.view,organization:advertiser_organization_id');

        Route::get('/campaigns', [CampaignsController::class, 'index'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.view,organization:advertiser_organization_id');
        Route::post('/campaigns', [CampaignsController::class, 'store'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        // docs/18-reporting-statistiques-audit-observabilite-wasplex.md §26
        // (comparaison) : doit être déclarée avant /campaigns/{campaign}
        // pour ne pas être capturée par le paramètre générique.
        Route::get('/campaigns/report', [CampaignReportingController::class, 'index'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.view,organization:advertiser_organization_id');
        Route::get('/campaigns/{campaign}', [CampaignsController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.view,organization:advertiser_organization_id');
        Route::get('/campaigns/{campaign}/report', [CampaignReportingController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.view,organization:advertiser_organization_id');
        Route::patch('/campaigns/{campaign}', [CampaignsController::class, 'update'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        Route::post('/campaigns/{campaign}/estimate-audience', [CampaignsController::class, 'estimateAudience'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        Route::post('/campaigns/{campaign}/quote', [CampaignsController::class, 'quote'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        Route::post('/campaigns/{campaign}/fund', [CampaignsController::class, 'fund'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        Route::post('/campaigns/{campaign}/submit', [CampaignsController::class, 'submit'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.submit,organization:advertiser_organization_id');
        Route::post('/campaigns/{campaign}/resubmit', [CampaignsController::class, 'resubmit'])
            ->middleware(EnsureCapability::class.':advertiser.campaign.submit,organization:advertiser_organization_id');
    });

// docs/05-modele-economique-publicitaire-wasplex.md §30 : catalogue de prix.
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/advertising')
    ->group(function (): void {
        Route::get('/pricing', [PricingController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.advertising.pricing.manage');
        Route::post('/pricing', [PricingController::class, 'store'])
            ->middleware(EnsureCapability::class.':admin.advertising.pricing.manage');
        Route::patch('/pricing/{priceVersion}', [PricingController::class, 'update'])
            ->middleware(EnsureCapability::class.':admin.advertising.pricing.manage');
        Route::post('/pricing/{priceVersion}/publish', [PricingController::class, 'publish'])
            ->middleware(EnsureCapability::class.':admin.advertising.pricing.manage');
    });

// docs/13-studio-annonceur-wasplex.md §93 : revue administrative des
// campagnes (P007).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/campaign-reviews', [CampaignReviewsController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.campaign-reviews.view');
        Route::get('/campaign-reviews/{campaignReview}', [CampaignReviewsController::class, 'show'])
            ->middleware(EnsureCapability::class.':admin.campaign-reviews.view');
        Route::post('/campaign-reviews/{campaignReview}/approve', [CampaignReviewsController::class, 'approve'])
            ->middleware(EnsureCapability::class.':admin.campaign-reviews.decide');
        Route::post('/campaign-reviews/{campaignReview}/request-changes', [CampaignReviewsController::class, 'requestChanges'])
            ->middleware(EnsureCapability::class.':admin.campaign-reviews.decide');
        Route::post('/campaign-reviews/{campaignReview}/reject', [CampaignReviewsController::class, 'reject'])
            ->middleware(EnsureCapability::class.':admin.campaign-reviews.decide');
        Route::get('/campaigns/approved', [CampaignReviewsController::class, 'approved'])
            ->middleware(EnsureCapability::class.':admin.campaigns.suspend');
        Route::post('/campaigns/{campaign}/suspend', [CampaignReviewsController::class, 'suspend'])
            ->middleware(EnsureCapability::class.':admin.campaigns.suspend');
    });
