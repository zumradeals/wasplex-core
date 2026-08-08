<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Application\Contracts\SubscriptionsReportingContract;
use App\Modules\Subscriptions\Application\Services\SubscriptionService;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Support\Facades\Artisan;

/**
 * Docs/chantiers/P017-CHANTIER.md (écran Utilisateurs) : nouvelle méthode
 * de lecture ajoutée à un contrat déjà réel et déjà consommé en
 * cross-module (précédent P012) — pas un nouveau contrat inventé.
 */
beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
});

it('returns null for an account with no subscription at all', function (): void {
    registerAndLogin('no-sub@wasplex.test');
    $accountId = Account::query()->firstOrFail()->id;

    expect(app(SubscriptionsReportingContract::class)->activeEconomicClassCodeForAccount($accountId))->toBeNull();
});

it('returns the real economic class code for an active subscription', function (): void {
    registerAndLogin('free-sub@wasplex.test');
    $accountId = Account::query()->firstOrFail()->id;

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    app(SubscriptionService::class)->subscribe($accountId, $free->id);

    expect(app(SubscriptionsReportingContract::class)->activeEconomicClassCodeForAccount($accountId))->toBe('FREE');
});
