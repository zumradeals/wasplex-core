<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Application\Services\LiveRewardAttentionService;
use App\Modules\Live\Application\Services\LiveRewardHoldService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
use App\Modules\Live\Infrastructure\Models\LiveRewardHold;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    config([
        'live.minimum_segment_size' => 2,
        'live.reward_offer_seconds' => 45,
        'live.rewards_enabled' => true,
        'live.reward_antifraud_mode' => 'observe',
        'live.reward_risk_repeat_threshold' => 3,
        'live.reward_heartbeat_min_interval_ms' => 100,
        'live.reward_heartbeat_max_gap_ms' => 200000,
    ]);
    config()->set('services.livekit.url', 'wss://live.test');
    config()->set('services.livekit.api_key', 'devkey');
    config()->set('services.livekit.api_secret', 'test-secret');
});

function p018fAudience(int $count = 8): void
{
    $economicClass = EconomicClass::query()->where('code', 'FREE')->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()->whereHas('plan', fn ($query) => $query->where('code', 'FREE'))->firstOrFail();
    foreach (range(1, $count) as $index) {
        $account = Account::query()->create([
            'status' => 'verified',
            'password' => bcrypt('Password123!'),
            'country_code' => 'CI',
            'language' => 'fr',
            'timezone' => 'UTC',
        ]);
        UserSubscription::query()->create([
            'account_id' => $account->id,
            'plan_version_id' => $planVersion->id,
            'economic_class_id' => $economicClass->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'started_at' => now(),
            'current_period_end' => null,
        ]);
    }
}

function p018fCreditAdvertiser(string $organizationId, int $amountMinor = 100000): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_P018F_CREDIT',
        sourceModule: 'test',
        idempotencyKey: 'test-p018f-credit:'.$organizationId.':'.uniqid(),
        entries: [
            LedgerEntryInput::debit(LedgerAccountReference::system('wasplex.cash.clearing', 'ASSET', 'WP'), Money::of($amountMinor, 'WP')),
            LedgerEntryInput::credit(
                LedgerAccountReference::forOrganization(AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE, $organizationId, 'LIABILITY_ADVERTISER', 'WP'),
                Money::of($amountMinor, 'WP'),
            ),
        ],
    ));
}

/** @return array{live_id: string, organization_id: string} */
function p018fFundedLive(int $capacity = 1, int $maxBlocks = 2): array
{
    p018fAudience();
    registerAndLogin('p018f-host-'.uniqid().'@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur P018-F '.uniqid());
    p018fCreditAdvertiser($organizationId);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live P018-F',
        'planned_duration_minutes' => 10,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", [
        'country_code' => 'CI',
        'economic_classes' => ['FREE'],
    ])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/quote", [
        'budget_amount_minor' => 10000,
        'block_duration_seconds' => 120,
        'rewarded_seat_capacity' => $capacity,
        'max_blocks_per_viewer' => $maxBlocks,
    ])->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/fund")->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    return ['live_id' => $liveId, 'organization_id' => $organizationId];
}

function p018fAccount(string $email): Account
{
    return AccountIdentifier::query()->where('type', 'email')->where('value', $email)->firstOrFail()->account;
}

function p018fHeartbeat(string $liveId)
{
    return test()->postJson("/api/lives/{$liveId}/reward-attention/heartbeat", [
        'visible' => true,
        'media_connected' => true,
    ]);
}

function p018fCreateHeldBlock(string $liveId, string $email = 'p018f-held@example.com'): LiveRewardHold
{
    config(['live.reward_antifraud_mode' => 'enforce']);
    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin($email, country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk()->assertJsonPath('reward_seat.viewer.status', 'rewarded');
    p018fHeartbeat($liveId)->assertOk();
    p018fHeartbeat($liveId)->assertOk();
    p018fHeartbeat($liveId)->assertOk();
    p018fHeartbeat($liveId)->assertOk();
    test()->travel(120)->seconds();
    p018fHeartbeat($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.pending_review_blocks', 1)
        ->assertJsonPath('reward_attention.earned_minor', 0);

    return LiveRewardHold::query()->where('live_id', $liveId)->firstOrFail();
}

it('never rewards the advertiser organization from its own sponsored Live even in observe mode', function (): void {
    ['live_id' => $liveId, 'organization_id' => $organizationId] = p018fFundedLive();
    test()->postJson('/api/logout')->assertNoContent();
    $email = 'p018f-own-member@example.com';
    registerAndLogin($email, country: 'CI');
    $viewer = p018fAccount($email);
    OrganizationMembership::query()->create([
        'organization_id' => $organizationId,
        'account_id' => $viewer->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    p018fHeartbeat($liveId)->assertOk()->assertJsonPath('reward_attention.status', 'unavailable')->assertJsonPath('reward_attention.earned_minor', 0);
    test()->travel(120)->seconds();
    p018fHeartbeat($liveId)->assertOk()->assertJsonPath('reward_attention.earned_minor', 0);
    expect(app(UserWalletContract::class)->balanceMinor($viewer->id))->toBe(0)
        ->and(LiveRewardAttentionBlock::query()->where('live_id', $liveId)->count())->toBe(0)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_REWARD_BLOCK_CAPTURED')->count())->toBe(0);
});

it('holds a high-risk completed block in enforce mode without touching the member Wallet', function (): void {
    ['live_id' => $liveId] = p018fFundedLive();
    $hold = p018fCreateHeldBlock($liveId);
    $viewer = p018fAccount('p018f-held@example.com');
    expect($hold->status)->toBe(LiveRewardHold::STATUS_PENDING)
        ->and($hold->reward_minor)->toBe(2500)
        ->and(app(UserWalletContract::class)->balanceMinor($viewer->id))->toBe(0)
        ->and(LiveRewardAttentionBlock::query()->where('live_id', $liveId)->where('status', LiveRewardAttentionBlock::STATUS_HELD)->count())->toBe(1)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_REWARD_BLOCK_CAPTURED')->count())->toBe(0);
});

it('releases a hold through the Ledger exactly once', function (): void {
    ['live_id' => $liveId] = p018fFundedLive();
    $hold = p018fCreateHeldBlock($liveId, 'p018f-release@example.com');
    $viewer = p018fAccount('p018f-release@example.com');
    $service = app(LiveRewardHoldService::class);
    $service->release($hold->id, $viewer->id, 'Contrôle manuel validé');
    $service->release($hold->id, $viewer->id, 'Nouvelle tentative');
    expect(app(UserWalletContract::class)->balanceMinor($viewer->id))->toBe(2500)
        ->and(LiveRewardHold::query()->findOrFail($hold->id)->status)->toBe(LiveRewardHold::STATUS_RELEASED)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_REWARD_BLOCK_CAPTURED')->count())->toBe(1);
});

it('keeps pending hold money reserved when an ended Live settles, then releases it after rejection', function (): void {
    ['live_id' => $liveId, 'organization_id' => $organizationId] = p018fFundedLive();
    $hold = p018fCreateHeldBlock($liveId, 'p018f-settlement-hold@example.com');
    $live = LiveEvent::query()->findOrFail($liveId);
    $live->update(['status' => LiveEvent::STATUS_ENDED, 'ended_at' => now()]);
    $report = app(LiveRewardAttentionService::class)->settleLive($live);
    expect($report['settled'])->toBeFalse()
        ->and($report['settlement_state'])->toBe('pending_review')
        ->and($report['pending_review_gross_minor'])->toBe(5000)
        ->and($report['released_minor'])->toBe(5000)
        ->and($report['remaining_reserved_minor'])->toBe(5000)
        ->and(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(95000);
    app(LiveRewardHoldService::class)->reject($hold->id, p018fAccount('p018f-settlement-hold@example.com')->id, 'Signal confirmé');
    $final = app(LiveRewardAttentionService::class)->report($live);
    expect($final['settled'])->toBeTrue()
        ->and($final['remaining_reserved_minor'])->toBe(0)
        ->and($final['released_minor'])->toBe(10000)
        ->and(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(100000)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_BUDGET_RELEASED')->count())->toBe(2);
});

it('kill switch stops new rewarded attention without rewriting past Ledger entries', function (): void {
    ['live_id' => $liveId] = p018fFundedLive();
    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('p018f-kill-switch@example.com', country: 'CI');
    $viewer = p018fAccount('p018f-kill-switch@example.com');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    config(['live.rewards_enabled' => false]);
    p018fHeartbeat($liveId)->assertOk()->assertJsonPath('reward_attention.status', 'rewards_paused')->assertJsonPath('reward_attention.rewards_paused', true);
    test()->travel(120)->seconds();
    p018fHeartbeat($liveId)->assertOk()->assertJsonPath('reward_attention.earned_minor', 0);
    expect(app(UserWalletContract::class)->balanceMinor($viewer->id))->toBe(0)
        ->and(LiveRewardAttentionBlock::query()->where('live_id', $liveId)->count())->toBe(0);
});

it('member reward state exposes pending review amount but never antifraud evidence or rule codes', function (): void {
    ['live_id' => $liveId] = p018fFundedLive();
    p018fCreateHeldBlock($liveId, 'p018f-privacy@example.com');
    $response = test()->getJson("/api/lives/{$liveId}/reward-attention")
        ->assertOk()
        ->assertJsonPath('reward_attention.pending_review_minor', 2500);
    $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('risk_codes')->and($json)->not->toContain('evidence')->and($json)->not->toContain('heartbeat_rate_abuse');
});
