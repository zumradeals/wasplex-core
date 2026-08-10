<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Application\Contracts\LedgerReportingContract;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

/**
 * docs/18-reporting-statistiques-audit-observabilite-wasplex.md §109 :
 * « première verticale à livrer » — reporting Studio et dashboard
 * fondateur construits sur les données réelles déjà produites par les
 * chantiers précédents (Campaigns, Feed, Ledger, Subscriptions).
 */
beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

function reportingAccountFor(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function loginExistingAccountForReportingTests(string $identifierValue, string $password = 'Password123!'): void
{
    test()->withCredentials();

    $login = test()->postJson('/api/login', ['identifier_value' => $identifierValue, 'password' => $password])->assertOk();

    $sessionCookieName = config('session.cookie');

    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }
}

/**
 * A fresh login defaults to the account's default space (usually
 * 'user') — the advertiser space switch made during
 * approvedCampaignForMatchingTests() lived only in that earlier, now
 * logged-out session, so report endpoints gated by
 * EnsureActiveAdvertiserOrganization need it repeated here.
 */
function switchToAdvertiserSpaceForReportingTests(string $organizationId): void
{
    $spaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    $advertiserSpace = collect($spaces)->firstWhere('organization_id', $organizationId);
    test()->postJson("/api/me/spaces/{$advertiserSpace['user_space_id']}/switch")->assertOk();
}

function verifyRecentMfaForReportingTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

/**
 * Runs the full P009 vertical for one candidate — reservation through
 * completion — against an approved, funded campaign, so a real delivery
 * with a real Ledger transaction backs the reporting assertions.
 */
function completeOneFeedDeliveryForReportingTests(string $campaignId, string $candidateEmail): int
{
    registerAndLogin($candidateEmail);
    subscribeAccountToClassForMatchingTests(reportingAccountFor($candidateEmail)->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    expect($next)->not->toBeNull();
    expect($next['campaign_id'])->toBe($campaignId);

    test()->postJson("/api/feed/deliveries/{$next['id']}/start")->assertOk();

    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($next['required_duration_ms'] + 500));
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $next['required_duration_ms']])->assertOk();
    Carbon::setTestNow();

    $completed = test()->postJson("/api/feed/deliveries/{$next['id']}/complete")->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    return (int) $completed->json('gain_minor');
}

it('reports real budget and delivery numbers for a campaign after a full vertical', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'reporting-campaign-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $gainMinor = completeOneFeedDeliveryForReportingTests($campaignId, 'reporting-campaign-candidate@example.com');
    expect($gainMinor)->toBeGreaterThan(0);

    loginExistingAccountForReportingTests('reporting-campaign-advertiser@example.com');
    switchToAdvertiserSpaceForReportingTests($organizationId);

    $report = test()->getJson("/api/advertiser/campaigns/{$campaignId}/report")->assertOk()->json('report');

    expect($report['status'])->toBe('approved');
    expect($report['feed']['total_deliveries'])->toBe(1);
    expect($report['feed']['completed'])->toBe(1);
    expect($report['feed']['gain_distributed_minor'])->toBe($gainMinor);
    expect($report['budget_captured_minor'])->toBe($gainMinor * 2);
    expect((float) $report['feed']['attention_rate'])->toEqual(1.0);
});

it('returns zeroed stats for a campaign with no deliveries, without error', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'reporting-empty-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    loginExistingAccountForReportingTests('reporting-empty-advertiser@example.com');
    switchToAdvertiserSpaceForReportingTests($organizationId);

    $report = test()->getJson("/api/advertiser/campaigns/{$campaignId}/report")->assertOk()->json('report');

    expect($report['feed']['total_deliveries'])->toBe(0);
    expect((float) $report['feed']['attention_rate'])->toEqual(0.0);
    expect($report['budget_captured_minor'])->toBe(0);
});

it('lists every campaign of an organization in the comparison endpoint', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'reporting-org-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    loginExistingAccountForReportingTests('reporting-org-advertiser@example.com');
    switchToAdvertiserSpaceForReportingTests($organizationId);

    $reports = test()->getJson('/api/advertiser/campaigns/report')->assertOk()->json('reports');

    expect(collect($reports)->pluck('campaign_id'))->toContain($campaignId);
});

it('refuses an advertiser reading a campaign report belonging to another organization', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'reporting-owner-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    registerAndLogin('reporting-intruder-advertiser@example.com');
    createAdvertiserOrganization();

    test()->getJson("/api/advertiser/campaigns/{$campaignId}/report")->assertStatus(404);
});

it('always reports a balanced Grand Livre', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'reporting-balance-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );
    completeOneFeedDeliveryForReportingTests($campaignId, 'reporting-balance-candidate@example.com');

    $summary = app(LedgerReportingContract::class)->globalSummary();

    expect($summary->totalDebitMinor)->toBe($summary->totalCreditMinor);
    expect($summary->isBalanced())->toBeTrue();
});

it('reflects a known scenario in the founder dashboard summary', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'reporting-dashboard-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );
    $gainMinor = completeOneFeedDeliveryForReportingTests($campaignId, 'reporting-dashboard-candidate@example.com');

    $adminEmail = 'reporting-dashboard-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(reportingAccountFor($adminEmail), ['admin.dashboard.view']);
    verifyRecentMfaForReportingTests();

    $summary = test()->getJson('/api/admin/dashboard/summary')->assertOk();

    expect($summary->json('ledger.is_balanced'))->toBeTrue();
    expect($summary->json('ledger.net_by_account_type.LIABILITY_USER'))->toBe($gainMinor);
    expect($summary->json('feed.completed'))->toBe(1);
    expect($summary->json('feed.gain_distributed_minor'))->toBe($gainMinor);
    expect($summary->json('campaigns.total_captured_minor'))->toBe($gainMinor * 2);
});

it('refuses the founder dashboard without the admin.dashboard.view capability', function (): void {
    registerAndLogin('reporting-nocap-admin@wasplex.test');
    grantFounderAccessForTests(reportingAccountFor('reporting-nocap-admin@wasplex.test'), []);
    verifyRecentMfaForReportingTests();

    test()->getJson('/api/admin/dashboard/summary')->assertStatus(403);
});
