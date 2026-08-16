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
use App\Modules\Live\Application\Services\LiveLifecycleService;
use App\Modules\Live\Application\Services\LiveRewardSeatService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;
use App\Modules\Live\Infrastructure\Models\LiveRewardWaitlistEntry;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    config(['live.minimum_segment_size' => 2, 'live.reward_offer_seconds' => 45]);
    config()->set('services.livekit.url', 'wss://live.test');
    config()->set('services.livekit.api_key', 'devkey');
    config()->set('services.livekit.api_secret', 'test-secret');
});

function createRewardSeatAudience(int $count = 8, string $country = 'CI', string $classCode = 'FREE'): void
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

function creditAdvertiserWalletForRewardSeatTests(string $organizationId, int $amountMinor): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_LIVE_REWARD_SEAT_CREDIT',
        sourceModule: 'test',
        idempotencyKey: 'test-live-reward-seat-credit:'.$organizationId.':'.uniqid(),
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
 * @return array{live_id: string, organization_id: string}
 */
function createFundedRewardSeatLive(int $capacity = 1, int $maxBlocks = 2): array
{
    createRewardSeatAudience();
    registerAndLogin('reward-seat-host-'.uniqid().'@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur Reward Seats '.uniqid());
    creditAdvertiserWalletForRewardSeatTests($organizationId, 100000);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Live places rémunérées',
        'planned_duration_minutes' => 10,
    ])->assertCreated()->json('live.id');

    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", [
        'country_code' => 'CI',
        'economic_classes' => ['FREE'],
    ])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/quote", [
        'budget_amount_minor' => 10000,
        'block_duration_seconds' => 300,
        'rewarded_seat_capacity' => $capacity,
        'max_blocks_per_viewer' => $maxBlocks,
    ])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/fund")->assertOk();
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    return ['live_id' => $liveId, 'organization_id' => $organizationId];
}

it('builds a deterministic funded seat plan without crediting any viewer', function (): void {
    createRewardSeatAudience();
    registerAndLogin('reward-seat-plan@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur Reward Plan');
    creditAdvertiserWalletForRewardSeatTests($organizationId, 100000);

    $liveId = (string) test()->postJson('/api/advertiser/lives', [
        'title' => 'Plan places rémunérées',
        'planned_duration_minutes' => 10,
    ])->assertCreated()->json('live.id');
    test()->patchJson("/api/advertiser/lives/{$liveId}/sponsorship", [
        'country_code' => 'CI',
        'economic_classes' => ['FREE'],
    ])->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/quote", [
        'budget_amount_minor' => 10000,
        'block_duration_seconds' => 300,
        'rewarded_seat_capacity' => 2,
        'max_blocks_per_viewer' => 2,
    ])->assertOk()
        ->assertJsonPath('live.sponsorship.latest_quote.rewarded_seat_capacity', 2)
        ->assertJsonPath('live.sponsorship.latest_quote.max_blocks_per_viewer', 2)
        ->assertJsonPath('live.sponsorship.latest_quote.funded_blocks', 4)
        ->assertJsonPath('live.sponsorship.latest_quote.reward_per_block_minor', 1250)
        ->assertJsonPath('live.sponsorship.latest_quote.max_reward_per_viewer_minor', 2500)
        ->assertJsonPath('live.sponsorship.latest_quote.spectator_envelope_remainder_minor', 0);

    expect(LedgerTransaction::query()->where('type', 'LIVE_REWARD_BLOCK_CAPTURED')->count())->toBe(0);
});

it('assigns a funded seat to the first eligible viewer and keeps the next viewer non rewarded', function (): void {
    ['live_id' => $liveId] = createFundedRewardSeatLive(1, 2);
    $transactionsAfterFunding = LedgerTransaction::query()->count();

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-viewer-one@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded')
        ->assertJsonPath('reward_seat.plan.rewarded_seat_capacity', 1);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-viewer-two@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'non_rewarded')
        ->assertJsonPath('reward_seat.viewer.can_join_waitlist', true);

    expect(LiveRewardSeat::query()->where('live_id', $liveId)->where('status', 'active')->count())->toBe(1)
        ->and(LedgerTransaction::query()->count())->toBe($transactionsAfterFunding);
});

it('promotes the waitlist in FIFO order when a rewarded seat is released', function (): void {
    ['live_id' => $liveId] = createFundedRewardSeatLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-fifo-one@example.com', country: 'CI');
    $viewerOne = AccountIdentifier::query()->where('type', 'email')->where('value', 'reward-seat-fifo-one@example.com')->firstOrFail()->account;
    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded');

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-fifo-two@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'waiting');

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-fifo-three@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'waiting');

    app(LiveRewardSeatService::class)->releaseForViewer(
        LiveEvent::query()->findOrFail($liveId),
        $viewerOne,
        'test_release',
    );

    $entries = LiveRewardWaitlistEntry::query()
        ->where('live_id', $liveId)
        ->orderBy('queued_at')
        ->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->status)->toBe(LiveRewardWaitlistEntry::STATUS_OFFERED)
        ->and($entries[1]->status)->toBe(LiveRewardWaitlistEntry::STATUS_WAITING);

    test()->getJson("/api/lives/{$liveId}/reward-seat")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'waiting');
});

it('accepts an offered seat but still performs no Wallet or Ledger reward credit', function (): void {
    ['live_id' => $liveId] = createFundedRewardSeatLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-owner-release@example.com', country: 'CI');
    $firstViewer = AccountIdentifier::query()->where('type', 'email')->where('value', 'reward-seat-owner-release@example.com')->firstOrFail()->account;
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-accept@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")->assertOk();

    $ledgerCount = LedgerTransaction::query()->count();
    app(LiveRewardSeatService::class)->releaseForViewer(
        LiveEvent::query()->findOrFail($liveId),
        $firstViewer,
        'test_release',
    );

    test()->postJson("/api/lives/{$liveId}/reward-seat/accept")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded');

    expect(LiveRewardSeat::query()
        ->where('live_id', $liveId)
        ->where('status', LiveRewardSeat::STATUS_ACTIVE)
        ->count())->toBe(1)
        ->and(LedgerTransaction::query()->count())->toBe($ledgerCount);
});

it('keeps an ineligible country as a normal spectator and refuses the reward waitlist', function (): void {
    ['live_id' => $liveId] = createFundedRewardSeatLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-country@example.com', country: 'SN');

    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'ineligible');

    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")
        ->assertForbidden()
        ->assertJsonPath('code', 'LIVE_REWARD_NOT_ELIGIBLE');
});

it('exposes only safe reward terms to the member API', function (): void {
    ['live_id' => $liveId] = createFundedRewardSeatLive(2, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-safe-api@example.com', country: 'CI');

    test()->getJson("/api/lives/{$liveId}")
        ->assertOk()
        ->assertJsonPath('live.reward_plan.rewarded_seat_capacity', 2)
        ->assertJsonPath('live.reward_plan.max_blocks_per_viewer', 2)
        ->assertJsonPath('live.sponsorship', null)
        ->assertJsonMissingPath('live.reward_plan.budget_amount_minor')
        ->assertJsonMissingPath('live.reward_plan.segment')
        ->assertJsonMissingPath('live.reward_plan.ledger_transaction_id');
});

it('expires an unaccepted offer and proposes the seat to the next active viewer', function (): void {
    ['live_id' => $liveId] = createFundedRewardSeatLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-expiry-one@example.com', country: 'CI');
    $firstViewer = AccountIdentifier::query()->where('type', 'email')->where('value', 'reward-seat-expiry-one@example.com')->firstOrFail()->account;
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-expiry-two@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")->assertOk();

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-expiry-three@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")->assertOk();

    app(LiveRewardSeatService::class)->releaseForViewer(
        LiveEvent::query()->findOrFail($liveId),
        $firstViewer,
        'test_release',
    );

    test()->travel(46)->seconds();

    test()->getJson("/api/lives/{$liveId}/reward-seat")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'offered');

    $entries = LiveRewardWaitlistEntry::query()
        ->where('live_id', $liveId)
        ->orderBy('queued_at')
        ->get();

    expect($entries[0]->status)->toBe(LiveRewardWaitlistEntry::STATUS_EXPIRED)
        ->and($entries[1]->status)->toBe(LiveRewardWaitlistEntry::STATUS_OFFERED);
});

it('keeps an active rewarded seat while the Live is paused', function (): void {
    ['live_id' => $liveId, 'organization_id' => $organizationId] = createFundedRewardSeatLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-pause@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('reward_seat.viewer.status', 'rewarded');

    $live = LiveEvent::query()->findOrFail($liveId);
    $host = Account::query()->findOrFail($live->owner_account_id);
    app(LiveLifecycleService::class)->pause($live, $host, $organizationId);

    expect(LiveRewardSeat::query()
        ->where('live_id', $liveId)
        ->where('status', LiveRewardSeat::STATUS_ACTIVE)
        ->count())->toBe(1);
});

it('closes active seats and pending waitlist entries when the Live ends', function (): void {
    ['live_id' => $liveId, 'organization_id' => $organizationId] = createFundedRewardSeatLive(1, 2);

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-end-one@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    test()->postJson('/api/logout')->assertNoContent();
    registerAndLogin('reward-seat-end-two@example.com', country: 'CI');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    test()->postJson("/api/lives/{$liveId}/reward-seat/waitlist")->assertOk();

    $live = LiveEvent::query()->findOrFail($liveId);
    $host = Account::query()->findOrFail($live->owner_account_id);
    app(LiveLifecycleService::class)->end($live, $host, $organizationId);
    app(LiveRewardSeatService::class)->closeLive($live->refresh());

    expect(LiveRewardSeat::query()->where('live_id', $liveId)->value('status'))
        ->toBe(LiveRewardSeat::STATUS_COMPLETED)
        ->and(LiveRewardWaitlistEntry::query()->where('live_id', $liveId)->value('status'))
        ->toBe(LiveRewardWaitlistEntry::STATUS_CANCELLED);
});
