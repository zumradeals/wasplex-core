<?php

declare(strict_types=1);

use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Wallet\Events\WalletBalanceChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

/**
 * docs/chantiers/P011-CHANTIER.md §5 : `wallet.balance.changed` n'est diffusé
 * que lorsque le solde change réellement — jamais sur une mise en attente
 * antifraude, un rejet, ou une relecture idempotente d'un `complete()` déjà
 * traité.
 */
beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('broadcasts wallet.balance.changed on the account private channel after a normal Feed credit', function (): void {
    Event::fake([WalletBalanceChanged::class]);

    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-realtime-credit-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );
    $candidate = readyFeedCandidate('feed-realtime-credit-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();

    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$next['id']}/start")->assertOk();

    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($next['required_duration_ms'] + 500));
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $next['required_duration_ms']])->assertOk();
    Carbon::setTestNow();

    $completed = test()->postJson("/api/feed/deliveries/{$next['id']}/complete")->assertOk();
    $delivery = FeedAdDelivery::query()->findOrFail($next['id']);

    Event::assertDispatched(WalletBalanceChanged::class, function (WalletBalanceChanged $event) use ($candidate, $delivery, $completed): bool {
        return $event->accountId === $candidate->id
            && $event->amountMinor === $completed->json('gain_minor')
            && $event->balanceMinor === $completed->json('balance_minor')
            && $event->origin === 'feed.attention'
            && $event->operation === 'credit'
            && $event->ledgerTransactionId === $delivery->ledger_transaction_id
            && $event->broadcastOn()[0]->name === "private-wallet.{$candidate->id}";
    });

    // Replaying complete() on an already-completed delivery must not
    // dispatch a second notification — no balance change occurs.
    Event::fake([WalletBalanceChanged::class]);
    test()->postJson("/api/feed/deliveries/{$next['id']}/complete")->assertOk();
    Event::assertNotDispatched(WalletBalanceChanged::class);
});

/**
 * Freezes time, starts a delivery, and drives it into `held` via the same
 * heartbeat-rate-abuse signal as FeedAntifraudTest, then logs the admin out
 * so a fresh admin session can review it (mirrors heldDeliveryForReviewTests).
 */
function heldDeliveryForRealtimeTests(string $advertiserEmail, string $candidateEmail): array
{
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        $advertiserEmail,
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    $candidate = readyFeedCandidate($candidateEmail, 'GOLD');
    $sessionId = startFeedSession();

    $base = Carbon::now('UTC');
    Carbon::setTestNow($base);

    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$next['id']}/start")->assertOk();
    $requiredMs = $next['required_duration_ms'];

    Carbon::setTestNow($base->clone()->addMilliseconds($requiredMs + 500));
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $requiredMs])->assertOk();
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $requiredMs])->assertOk();
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $requiredMs])->assertOk();

    Carbon::setTestNow();

    $completed = test()->postJson("/api/feed/deliveries/{$next['id']}/complete")->assertOk();
    expect($completed->json('delivery.status'))->toBe('held');

    $delivery = FeedAdDelivery::query()->findOrFail($next['id']);
    $hold = FeedAdDeliveryHold::query()->where('feed_ad_delivery_id', $delivery->id)->firstOrFail();

    test()->postJson('/api/logout')->assertNoContent();

    return ['delivery' => $delivery, 'hold' => $hold, 'candidate' => $candidate];
}

it('does not broadcast when a Feed delivery is placed on hold', function (): void {
    Event::fake([WalletBalanceChanged::class]);

    heldDeliveryForRealtimeTests('feed-realtime-hold-advertiser@example.com', 'feed-realtime-hold-candidate@example.com');

    Event::assertNotDispatched(WalletBalanceChanged::class);
});

it('broadcasts wallet.balance.changed when an admin releases a held delivery', function (): void {
    $ctx = heldDeliveryForRealtimeTests('feed-realtime-release-advertiser@example.com', 'feed-realtime-release-candidate@example.com');

    $adminEmail = 'feed-realtime-release-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), ['admin.feed.risk.review']);
    verifyRecentMfaForFeedTests();

    Event::fake([WalletBalanceChanged::class]);

    test()->postJson("/api/admin/feed/holds/{$ctx['hold']->id}/release")->assertOk();

    $delivery = $ctx['delivery']->refresh();

    Event::assertDispatched(WalletBalanceChanged::class, function (WalletBalanceChanged $event) use ($ctx, $delivery): bool {
        return $event->accountId === $ctx['candidate']->id
            && $event->amountMinor === $delivery->gain_minor
            && $event->origin === 'feed.risk_review'
            && $event->operation === 'credit'
            && $event->ledgerTransactionId === $delivery->ledger_transaction_id;
    });
});

it('does not broadcast when an admin rejects a held delivery', function (): void {
    $ctx = heldDeliveryForRealtimeTests('feed-realtime-reject-advertiser@example.com', 'feed-realtime-reject-candidate@example.com');

    $adminEmail = 'feed-realtime-reject-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), ['admin.feed.risk.review']);
    verifyRecentMfaForFeedTests();

    Event::fake([WalletBalanceChanged::class]);

    test()->postJson("/api/admin/feed/holds/{$ctx['hold']->id}/reject")->assertOk();

    Event::assertNotDispatched(WalletBalanceChanged::class);
});
