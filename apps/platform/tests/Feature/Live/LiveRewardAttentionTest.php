<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Application\Services\LiveRewardAttentionService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
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
        'live.reward_heartbeat_min_interval_ms' => 100,
        'live.reward_heartbeat_max_gap_ms' => 200000,
    ]);
    config()->set('services.livekit.url', 'wss://live.test');
    config()->set('services.livekit.api_key', 'devkey');
    config()->set('services.livekit.api_secret', 'test-secret');
});

function createLiveAttentionAudience(int $count = 8, string $country = 'CI', string $classCode = 'FREE'): void
{
    $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($query) => $query->where('code', $classCode))
        ->firstOrFail();

    foreach (range(1, $count) as $index) {
        $account = Account::query()->create([
            'status' => 'verified',
            'password' => bcrypt('Password123!'),
            'country_code' => $country,
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

function creditAdvertiserWalletForLiveAttention(string $organizationId, int $amountMinor): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_LIVE_ATTENTION_CREDIT',
        sourceModule: 'test',
        idempotencyKey: 'test-live-attention-credit:'.$organizationId.':'.uniqid(),
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

/**
 * @return array{live_id: string, organization_id: string, host_email: string}
 */
function createFundedLiveAttentionLive(int $capacity = 1, int $maxBlocks = 2): array
{
    createLiveAttentionAudience();

    $hostEmail = 'live-attention-host-'.uniqid().'@example.com';
    registerAndLogin($hostEmail);
    $organizationId = createAdvertiserOrganization('Annonceur Live Attention '.uniqid());
    creditAdvertiserWalletForLiveAttention($organizationId, 100000);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live attention rémunérée',
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

    return [
        'live_id' => $liveId,
        'organization_id' => $organizationId,
        'host_email' => $hostEmail,
    ];
}

function liveAttentionAccount(string $email): Account
{
    return AccountIdentifier::query()
        ->where('type', 'email')
        ->where('value', $email)
        ->firstOrFail()
        ->account;
}

function heartbeatLiveAttention(string $liveId, bool $visible = true, bool $mediaConnected = true)
{
    return test()->postJson("/api/lives/{$liveId}/reward-attention/heartbeat", [
        'visible' => $visible,
        'media_connected' => $mediaConnected,
    ]);
}

it('captures one complete server-qualified block with an atomic 50/50 Ledger transaction', function (): void {
    ['live_id' => $liveId] = createFundedLiveAttentionLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    $viewerEmail = 'live-attention-capture@example.com';
    registerAndLogin($viewerEmail, country: 'CI');
    $viewer = liveAttentionAccount($viewerEmail);

    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded');

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.validated_blocks', 0);

    test()->travel(120)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.validated_blocks', 1)
        ->assertJsonPath('reward_attention.earned_minor', 2500)
        ->assertJsonPath('reward_attention.progress_percent', 0);

    $transaction = LedgerTransaction::query()
        ->where('type', 'LIVE_REWARD_BLOCK_CAPTURED')
        ->firstOrFail();

    expect(app(UserWalletContract::class)->balanceMinor($viewer->id))->toBe(2500)
        ->and(LiveRewardAttentionBlock::query()->where('live_id', $liveId)->count())->toBe(1)
        ->and($transaction->source_module)->toBe('live.attention');
});

it('does not count hidden or disconnected intervals and requires a fresh qualified baseline', function (): void {
    ['live_id' => $liveId] = createFundedLiveAttentionLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('live-attention-hidden@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    heartbeatLiveAttention($liveId)->assertOk();
    test()->travel(60)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.progress_percent', 50);

    heartbeatLiveAttention($liveId, false, true)
        ->assertOk()
        ->assertJsonPath('reward_attention.progress_percent', 0);

    test()->travel(120)->seconds();

    // Becoming visible again only establishes a fresh baseline.
    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.validated_blocks', 0)
        ->assertJsonPath('reward_attention.progress_percent', 0);

    test()->travel(120)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.validated_blocks', 1);

    expect(LiveRewardAttentionBlock::query()->where('live_id', $liveId)->count())->toBe(1);
});

it('preserves partial progress through a host pause without counting paused time', function (): void {
    ['live_id' => $liveId] = createFundedLiveAttentionLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('live-attention-pause@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    heartbeatLiveAttention($liveId)->assertOk();
    test()->travel(60)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.progress_percent', 50);

    LiveEvent::query()->whereKey($liveId)->update(['status' => LiveEvent::STATUS_PAUSED]);
    test()->travel(120)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.status', 'paused')
        ->assertJsonPath('reward_attention.progress_percent', 50)
        ->assertJsonPath('reward_attention.validated_blocks', 0);

    LiveEvent::query()->whereKey($liveId)->update(['status' => LiveEvent::STATUS_LIVE]);

    // Resume heartbeat resets the temporal baseline and keeps the 50 % already earned.
    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.progress_percent', 50);

    test()->travel(60)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.validated_blocks', 1);
});

it('enforces the globally funded block ceiling even when a rewarded seat changes viewer', function (): void {
    ['live_id' => $liveId] = createFundedLiveAttentionLive(1, 1);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('live-attention-first@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded');

    heartbeatLiveAttention($liveId)->assertOk();
    test()->travel(120)->seconds();
    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.validated_blocks', 1);

    test()->postJson("/api/lives/{$liveId}/leave")->assertOk();

    test()->postJson('/api/logout')->assertNoContent();
    $secondEmail = 'live-attention-second@example.com';
    registerAndLogin($secondEmail, country: 'CI');
    $second = liveAttentionAccount($secondEmail);

    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded');

    heartbeatLiveAttention($liveId)->assertOk();
    test()->travel(120)->seconds();

    heartbeatLiveAttention($liveId)
        ->assertOk()
        ->assertJsonPath('reward_attention.status', 'funding_exhausted')
        ->assertJsonPath('reward_attention.validated_blocks', 0)
        ->assertJsonPath('reward_attention.earned_minor', 0);

    expect(LiveRewardAttentionBlock::query()->where('live_id', $liveId)->count())->toBe(1)
        ->and(app(UserWalletContract::class)->balanceMinor($second->id))->toBe(0);
});

it('settles every unused reserved WP exactly once after the Live ends', function (): void {
    ['live_id' => $liveId, 'organization_id' => $organizationId] = createFundedLiveAttentionLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('live-attention-settlement@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    heartbeatLiveAttention($liveId)->assertOk();
    test()->travel(120)->seconds();
    heartbeatLiveAttention($liveId)->assertOk();

    $live = LiveEvent::query()->findOrFail($liveId);
    $live->update(['status' => LiveEvent::STATUS_ENDED, 'ended_at' => now()]);

    $report = app(LiveRewardAttentionService::class)->settleLive($live);
    $secondReport = app(LiveRewardAttentionService::class)->settleLive($live);

    expect($report['settled'])->toBeTrue()
        ->and($report['gross_consumed_minor'])->toBe(5000)
        ->and($report['released_minor'])->toBe(5000)
        ->and($secondReport['settled'])->toBeTrue()
        ->and($secondReport['released_minor'])->toBe(5000)
        ->and(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(95000)
        ->and(LedgerTransaction::query()->where('type', 'LIVE_BUDGET_RELEASED')->count())->toBe(1);
});
