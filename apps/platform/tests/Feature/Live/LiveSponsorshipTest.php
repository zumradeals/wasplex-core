<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardQuote;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    config(['live.minimum_segment_size' => 2]);
    config()->set('services.livekit.url', 'wss://live.test');
    config()->set('services.livekit.api_key', 'devkey');
    config()->set('services.livekit.api_secret', 'test-secret');
});

function creditAdvertiserWalletForLiveTests(string $organizationId, int $amountMinor): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_LIVE_ADVERTISER_CREDIT',
        sourceModule: 'test',
        idempotencyKey: 'test-live-credit:'.$organizationId.':'.uniqid(),
        entries: [
            LedgerEntryInput::debit(
                LedgerAccountReference::system('wasplex.cash.clearing', 'ASSET', 'WP'),
                Money::of($amountMinor, 'WP'),
            ),
            LedgerEntryInput::credit(
                LedgerAccountReference::forOrganization(
                    AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
                    $organizationId,
                    'LIABILITY_ADVERTISER',
                    'WP',
                ),
                Money::of($amountMinor, 'WP'),
            ),
        ],
    ));
}

function createAudienceForSponsoredLiveTests(): void
{
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('PLATINUM', 'CI');
    createActiveSubscriberInClass('PLATINUM', 'CI');
    createActiveSubscriberInClass('PLATINUM', 'CI');
}

it('turns a scheduled standard Live into a sponsored draft and blocks official scheduling before funding', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-gate@example.com');
    createAdvertiserOrganization('Annonceur Sponsor Gate');

    $created = test()->postJson('/api/advertiser/lives', [
        'title' => 'Live sponsorisé Gate',
        'scheduled_at' => now()->addHours(2)->toIso8601String(),
        'planned_duration_minutes' => 60,
    ])->assertCreated()
        ->assertJsonPath('live.type', LiveEvent::TYPE_STANDARD)
        ->assertJsonPath('live.status', LiveEvent::STATUS_SCHEDULED);

    $liveId = (string) $created->json('live.id');

    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", [
        'country_code' => 'CI',
    ])->assertOk()
        ->assertJsonPath('live.type', LiveEvent::TYPE_SPONSORED)
        ->assertJsonPath('live.status', LiveEvent::STATUS_DRAFT)
        ->assertJsonPath('live.sponsorship.status', LiveRewardCampaign::STATUS_DRAFT)
        ->assertJsonPath('live.sponsorship.can_schedule', false);

    test()->postJson("/api/advertiser/lives/{$liveId}/schedule", [
        'scheduled_at' => now()->addHours(3)->toIso8601String(),
    ])->assertStatus(409)
        ->assertJsonPath('code', 'LIVE_SPONSORED_FUNDING_REQUIRED');

    test()->postJson("/api/advertiser/lives/{$liveId}/start")
        ->assertStatus(409)
        ->assertJsonPath('code', 'LIVE_SPONSORED_FUNDING_REQUIRED');
});

it('estimates a protected audience and quotes an exact 50/50 sponsored budget', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-quote@example.com');
    createAdvertiserOrganization('Annonceur Sponsor Quote');

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live sponsorisé Quote',
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');

    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", [
        'country_code' => 'CI',
        'economic_classes' => ['GOLD', 'PLATINUM'],
    ])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/segment-estimate")
        ->assertOk()
        ->assertJsonPath('estimate.estimated_reach_min', 5)
        ->assertJsonPath('estimate.estimated_reach_max', 10)
        ->assertJsonPath('estimate.too_small', false);

    test()->postJson("/api/advertiser/lives/{$liveId}/quote", [
        'budget_amount_minor' => 100000,
        'block_duration_seconds' => 300,
    ])->assertOk()
        ->assertJsonPath('live.sponsorship.status', LiveRewardCampaign::STATUS_QUOTED)
        ->assertJsonPath('live.sponsorship.latest_quote.budget_amount_minor', 100000)
        ->assertJsonPath('live.sponsorship.latest_quote.wasplex_share_minor', 50000)
        ->assertJsonPath('live.sponsorship.latest_quote.spectator_envelope_minor', 50000)
        ->assertJsonPath('live.sponsorship.latest_quote.block_duration_seconds', 300);

    $quote = LiveRewardQuote::query()->firstOrFail();
    expect($quote->estimated_reach_min)->toBe(5)
        ->and($quote->estimated_reach_max)->toBe(10)
        ->and($quote->wasplex_share_minor)->toBe(50000)
        ->and($quote->spectator_envelope_minor)->toBe(50000);
});

it('rejects a sponsored quote that cannot be split exactly 50/50', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-odd-budget@example.com');
    createAdvertiserOrganization('Annonceur Budget Impair');

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live budget impair',
        'planned_duration_minutes' => 30,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/quote", [
        'budget_amount_minor' => 99999,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'LIVE_SPONSORED_BUDGET_INVALID');
});

it('reserves the advertiser budget on the Ledger and then allows official scheduling', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-fund@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur Sponsor Fund');
    creditAdvertiserWalletForLiveTests($organizationId, 200000);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live sponsorisé financé',
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/quote", ['budget_amount_minor' => 100000])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/fund")
        ->assertOk()
        ->assertJsonPath('live.sponsorship.status', LiveRewardCampaign::STATUS_FUNDS_RESERVED)
        ->assertJsonPath('live.sponsorship.can_schedule', true)
        ->assertJsonPath('live.sponsorship.reservation.amount_minor', 100000);

    expect(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(100000)
        ->and(LiveRewardBudgetReservation::query()->where('live_reward_campaign_id', LiveRewardCampaign::query()->value('id'))->count())->toBe(1)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_BUDGET_RESERVED')->count())->toBe(1);

    test()->postJson("/api/advertiser/lives/{$liveId}/fund")->assertOk();
    expect(LiveRewardBudgetReservation::query()->count())->toBe(1)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_BUDGET_RESERVED')->count())->toBe(1);

    test()->postJson("/api/advertiser/lives/{$liveId}/schedule", [
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_SCHEDULED);

    expect(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveRewardCampaignFunded')->exists())
        ->toBeTrue();
});

it('does not reserve a sponsored Live budget when the advertiser Wallet is insufficient', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-empty-wallet@example.com');
    createAdvertiserOrganization('Annonceur Wallet Vide');

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live sans budget',
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/quote", ['budget_amount_minor' => 100000])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/fund")
        ->assertUnprocessable()
        ->assertJsonPath('available_minor', 0)
        ->assertJsonPath('required_minor', 100000);

    expect(LiveRewardBudgetReservation::query()->count())->toBe(0);
});

it('releases a reserved sponsored budget when sponsorship is cancelled before the Live starts', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-cancel@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur Sponsor Cancel');
    creditAdvertiserWalletForLiveTests($organizationId, 100000);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live sponsorisé annulé',
        'scheduled_at' => now()->addHours(2)->toIso8601String(),
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/quote", ['budget_amount_minor' => 100000])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/fund")->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/sponsorship/cancel")
        ->assertOk()
        ->assertJsonPath('live.type', LiveEvent::TYPE_STANDARD)
        ->assertJsonPath('live.status', LiveEvent::STATUS_SCHEDULED)
        ->assertJsonPath('live.sponsorship', null);

    expect(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(100000)
        ->and(LiveRewardBudgetReservation::query()->value('status'))->toBe(LiveRewardBudgetReservation::STATUS_RELEASED)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_BUDGET_RELEASED')->count())->toBe(1);
});

it('keeps sponsored Live financing isolated between advertiser organizations', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-org-isolation@example.com');
    createAdvertiserOrganization('Organisation Sponsor A');

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live Sponsor Organisation A',
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();

    createAdvertiserOrganization('Organisation Sponsor B');

    test()->postJson("/api/advertiser/lives/{$liveId}/quote", ['budget_amount_minor' => 100000])
        ->assertForbidden();

    expect(LiveRewardCampaign::query()->where('live_id', $liveId)->count())->toBe(1);
});

it('refuses a sponsored quote when the protected audience is below the minimum segment size', function (): void {
    config(['live.minimum_segment_size' => 20]);
    registerAndLogin('live-sponsored-small-segment@example.com');
    createAdvertiserOrganization('Annonceur Petit Segment');

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live petit segment',
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/quote", ['budget_amount_minor' => 100000])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'LIVE_SPONSORED_AUDIENCE_TOO_SMALL');

    expect(LiveRewardQuote::query()->count())->toBe(0);
});

it('does not expose advertiser sponsorship management data on the member Live API', function (): void {
    createAudienceForSponsoredLiveTests();
    registerAndLogin('live-sponsored-private-owner@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur Sponsor Privé');
    creditAdvertiserWalletForLiveTests($organizationId, 100000);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live sponsorisé public protégé',
        'planned_duration_minutes' => 60,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", ['country_code' => 'CI'])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/quote", ['budget_amount_minor' => 100000])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/fund")->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/schedule", [
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertOk();

    registerAndLogin('live-sponsored-private-viewer@example.com');

    test()->getJson("/api/lives/{$liveId}")
        ->assertOk()
        ->assertJsonPath('live.type', LiveEvent::TYPE_SPONSORED)
        ->assertJsonPath('live.sponsorship', null)
        ->assertJsonMissingPath('live.sponsorship.latest_quote.budget_amount_minor');
});
