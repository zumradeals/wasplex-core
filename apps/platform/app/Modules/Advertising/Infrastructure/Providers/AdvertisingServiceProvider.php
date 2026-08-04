<?php

namespace App\Modules\Advertising\Infrastructure\Providers;

use App\Modules\Advertising\Console\Commands\BootstrapAdvertising;
use App\Modules\Advertising\Http\Controllers\AdvertisingMatchingController;
use App\Modules\Advertising\Http\Controllers\AdvertisingProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AdvertisingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::prefix('/mon-espace/profil-intelligent')
            ->name('advertising.profile.')
            ->middleware(['web', 'auth', 'identity.session', 'space.kind:user'])
            ->group(function (): void {
                Route::get('/', [AdvertisingProfileController::class, 'index'])
                    ->middleware([
                        'capability:advertising.profile.view.self',
                        'capability:advertising.consent.view.self',
                    ])
                    ->name('index');
                Route::post('/reponses', [AdvertisingProfileController::class, 'answer'])
                    ->middleware([
                        'capability:advertising.profile.manage.self',
                        'throttle:30,1',
                    ])
                    ->name('answers.store');
                Route::delete('/reponses/{questionCode}', [AdvertisingProfileController::class, 'remove'])
                    ->middleware([
                        'capability:advertising.profile.manage.self',
                        'throttle:20,1',
                    ])
                    ->name('answers.remove');
                Route::post('/consentements', [AdvertisingProfileController::class, 'consent'])
                    ->middleware([
                        'capability:advertising.consent.manage.self',
                        'throttle:20,1',
                    ])
                    ->name('consents.decide');
                Route::get('/pourquoi/{match}', [AdvertisingMatchingController::class, 'explanation'])
                    ->middleware('capability:advertising.explanation.view.self')
                    ->name('explanation');
            });

        Route::prefix('/studio')
            ->name('advertising.targeting.')
            ->middleware(['web', 'auth', 'identity.session', 'space.kind:advertiser'])
            ->group(function (): void {
                Route::get('/ciblage/taxonomies', [AdvertisingMatchingController::class, 'taxonomies'])
                    ->middleware('capability:advertiser.targeting.taxonomy.view')
                    ->name('taxonomies');
                Route::post('/campagnes/{campaign}/ciblage', [AdvertisingMatchingController::class, 'configure'])
                    ->middleware([
                        'capability:advertiser.segment.estimate',
                        'throttle:20,1',
                    ])
                    ->name('configure');
                Route::post('/campagnes/{campaign}/ciblage/estimation', [AdvertisingMatchingController::class, 'estimate'])
                    ->middleware([
                        'capability:advertiser.segment.estimate',
                        'throttle:12,1',
                    ])
                    ->name('estimate');
            });

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapAdvertising::class]);
        }
    }
}
