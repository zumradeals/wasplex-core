<?php

use App\Modules\AdvertiserStudio\Http\Controllers\AdvertiserStudioController;
use App\Modules\Campaign\Http\Controllers\CampaignController;
use App\Modules\EconomicConfiguration\Http\Controllers\EconomicConfigurationAdminController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MfaController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Identity\Http\Controllers\ShellController;
use App\Modules\Identity\Http\Controllers\SpaceController;
use App\Modules\Wallet\Http\Controllers\WalletAdminController;
use App\Modules\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::inertia('/connexion', 'Auth/Login')->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::inertia('/inscription', 'Auth/Register')->name('register');
    Route::post('/inscription', RegisterController::class)->middleware('throttle:4,1')->name('register.store');
});

Route::middleware(['auth', 'identity.session'])->group(function (): void {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/espace', [ShellController::class, 'home'])->name('space.home');
    Route::post('/espaces/annonceur', [SpaceController::class, 'activateAdvertiser'])->middleware('capability:advertiser.space.activate')->name('spaces.advertiser.activate');
    Route::post('/espaces/{space}/activer', [SpaceController::class, 'switch'])->name('spaces.switch');

    Route::get('/securite/mfa', [MfaController::class, 'setupPage'])->name('security.mfa.setup');
    Route::post('/securite/mfa', [MfaController::class, 'begin'])->name('security.mfa.begin');
    Route::post('/securite/mfa/confirmer', [MfaController::class, 'confirm'])->name('security.mfa.confirm');
    Route::get('/securite/mfa/verification', [MfaController::class, 'challengePage'])->name('security.mfa.challenge');
    Route::post('/securite/mfa/verification', [MfaController::class, 'verify'])->middleware('throttle:6,1')->name('security.mfa.verify');

    Route::get('/mon-espace', [ShellController::class, 'user'])->middleware(['space.kind:user', 'capability:account.self.manage'])->name('user.dashboard');
    Route::get('/wallet', [WalletController::class, 'show'])->middleware(['space.kind:user', 'capability:wallet.view.self'])->name('wallet.show');
    Route::post('/wallet/depot', [WalletController::class, 'storeDeposit'])->middleware(['space.kind:user', 'capability:wallet.deposit.create.self', 'throttle:6,1'])->name('wallet.deposit.store');

    Route::prefix('/studio')
        ->name('studio.')
        ->middleware('space.kind:advertiser')
        ->group(function (): void {
            Route::get('/', [AdvertiserStudioController::class, 'dashboard'])
                ->middleware('capability:advertiser.space.view')
                ->name('dashboard');
            Route::get('/profil', [AdvertiserStudioController::class, 'profile'])
                ->middleware('capability:advertiser.profile.view')
                ->name('profile');
            Route::patch('/profil', [AdvertiserStudioController::class, 'updateProfile'])
                ->middleware('capability:advertiser.profile.manage')
                ->name('profile.update');
            Route::get('/marques', [AdvertiserStudioController::class, 'brands'])
                ->middleware('capability:advertiser.brand.view')
                ->name('brands');
            Route::post('/marques', [AdvertiserStudioController::class, 'storeBrand'])
                ->middleware('capability:advertiser.brand.manage')
                ->name('brands.store');
            Route::patch('/marques/{brand}', [AdvertiserStudioController::class, 'updateBrand'])
                ->middleware('capability:advertiser.brand.manage')
                ->name('brands.update');
            Route::delete('/marques/{brand}', [AdvertiserStudioController::class, 'archiveBrand'])
                ->middleware('capability:advertiser.brand.manage')
                ->name('brands.archive');
            Route::get('/medias', [AdvertiserStudioController::class, 'media'])
                ->middleware('capability:advertiser.media.view')
                ->name('media');
            Route::post('/medias', [AdvertiserStudioController::class, 'storeMedia'])
                ->middleware(['capability:advertiser.media.upload', 'throttle:20,1'])
                ->name('media.store');
            Route::patch('/medias/{asset}', [AdvertiserStudioController::class, 'updateMedia'])
                ->middleware('capability:advertiser.media.manage')
                ->name('media.update');
            Route::delete('/medias/{asset}', [AdvertiserStudioController::class, 'archiveMedia'])
                ->middleware('capability:advertiser.media.manage')
                ->name('media.archive');

            Route::get('/campagnes', [CampaignController::class, 'index'])
                ->middleware('capability:advertiser.campaign.view')
                ->name('campaigns.index');
            Route::get('/campagnes/nouvelle', [CampaignController::class, 'createPage'])
                ->middleware('capability:advertiser.campaign.create')
                ->name('campaigns.create');
            Route::post('/campagnes', [CampaignController::class, 'store'])
                ->middleware(['capability:advertiser.campaign.create', 'throttle:30,1'])
                ->name('campaigns.store');
            Route::get('/campagnes/{campaign}/modifier', [CampaignController::class, 'edit'])
                ->middleware('capability:advertiser.campaign.manage')
                ->name('campaigns.edit');
            Route::patch('/campagnes/{campaign}', [CampaignController::class, 'update'])
                ->middleware(['capability:advertiser.campaign.manage', 'throttle:60,1'])
                ->name('campaigns.update');
            Route::post('/campagnes/{campaign}/devis', [CampaignController::class, 'quote'])
                ->middleware(['capability:advertiser.campaign.quote', 'throttle:20,1'])
                ->name('campaigns.quote');
            Route::post('/campagnes/{campaign}/devis/{quote}/financer', [CampaignController::class, 'fund'])
                ->middleware(['capability:advertiser.campaign.fund', 'capability:advertiser.wallet.view', 'throttle:10,1'])
                ->name('campaigns.fund');
            Route::post('/campagnes/{campaign}/soumettre', [CampaignController::class, 'submit'])
                ->middleware(['capability:advertiser.campaign.submit', 'throttle:10,1'])
                ->name('campaigns.submit');
            Route::delete('/campagnes/{campaign}', [CampaignController::class, 'cancel'])
                ->middleware('capability:advertiser.campaign.manage')
                ->name('campaigns.cancel');

            Route::get('/wallet', [WalletController::class, 'show'])
                ->middleware('capability:advertiser.wallet.view')
                ->name('wallet');
            Route::post('/wallet/depot', [WalletController::class, 'storeDeposit'])
                ->middleware(['capability:advertiser.wallet.fund', 'throttle:6,1'])
                ->name('wallet.deposit.store');
        });

    Route::get('/administration', [ShellController::class, 'admin'])->middleware(['space.kind:administration', 'mfa.recent', 'capability:admin.dashboard.view'])->name('admin.dashboard');
    Route::get('/administration/wallet', [WalletAdminController::class, 'page'])->middleware(['space.kind:administration', 'mfa.recent', 'capability:wallet.deposit.review'])->name('admin.wallet');

    Route::prefix('/administration/economie')
        ->name('admin.economy.')
        ->middleware(['space.kind:administration', 'mfa.recent'])
        ->group(function (): void {
            Route::get('/', [EconomicConfigurationAdminController::class, 'index'])
                ->middleware('capability:economic.configuration.view')
                ->name('index');
            Route::post('/repartition', [EconomicConfigurationAdminController::class, 'applyDistribution'])
                ->middleware([
                    'capability:economic.configuration.manage',
                    'capability:economic.configuration.approve',
                    'capability:economic.configuration.publish',
                ])
                ->name('distribution.apply');
            Route::post('/simuler', [EconomicConfigurationAdminController::class, 'simulate'])
                ->middleware('capability:economic.configuration.manage')
                ->name('simulate');
            Route::post('/classes/{economicClass}/versions', [EconomicConfigurationAdminController::class, 'store'])
                ->middleware('capability:economic.configuration.manage')
                ->name('versions.store');
            Route::post('/versions/{version}/approuver', [EconomicConfigurationAdminController::class, 'approve'])
                ->middleware('capability:economic.configuration.approve')
                ->name('versions.approve');
            Route::post('/versions/publier-ensemble', [EconomicConfigurationAdminController::class, 'publishMany'])
                ->middleware('capability:economic.configuration.publish')
                ->name('versions.publish-many');
            Route::post('/versions/{version}/publier', [EconomicConfigurationAdminController::class, 'publish'])
                ->middleware('capability:economic.configuration.publish')
                ->name('versions.publish');
            Route::post('/versions/{version}/suspendre', [EconomicConfigurationAdminController::class, 'suspend'])
                ->middleware('capability:economic.configuration.suspend')
                ->name('versions.suspend');
        });
});
