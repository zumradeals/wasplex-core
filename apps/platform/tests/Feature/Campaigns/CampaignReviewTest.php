<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    config(['campaigns.minimum_segment_size' => 2]);
});

function accountForCampaignReviewTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForCampaignReviewTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

/**
 * Logs an already-registered account back in — registerAndLogin() always
 * re-registers, which fails for an identity used more than once across a
 * single test (e.g. the same admin acting twice, or the advertiser
 * returning after logging out to let an admin act).
 */
function loginOnlyForCampaignReviewTests(string $identifierValue, string $password = 'Password123!'): void
{
    test()->withCredentials();

    $login = test()->postJson('/api/login', [
        'identifier_value' => $identifierValue,
        'password' => $password,
    ])->assertOk();

    $sessionCookieName = config('session.cookie');

    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }
}

/**
 * "Active space" lives in the session (SpaceService::activeSpace()), so
 * logging back out and in loses it — the advertiser must explicitly
 * switch back to their organization's space before touching
 * advertiser-scoped routes again.
 */
function switchToAdvertiserSpaceForCampaignReviewTests(string $organizationId): void
{
    $spaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    $advertiserSpace = collect($spaces)->firstWhere('organization_id', $organizationId);
    test()->postJson("/api/me/spaces/{$advertiserSpace['user_space_id']}/switch")->assertOk();
}

/**
 * Full advertiser-side path to a submitted campaign (draft -> quoted ->
 * funded -> submitted), reusing the helpers already declared in
 * CampaignWizardTest.php / CampaignQuoteAndFundingTest.php (loaded
 * together by Pest).
 */
function submitCampaignForReview(string $email, int $budgetAmountMinor = 100000): array
{
    publishPriceCatalog();
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId] = setUpQuotableCampaign($email, $budgetAmountMinor);
    creditAdvertiserWalletForTests($organizationId, $budgetAmountMinor * 2);

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/submit")->assertOk();

    $caseId = CampaignReviewCase::query()->where('campaign_id', $campaignId)->orderByDesc('opened_at')->orderByDesc('id')->firstOrFail()->id;

    return ['organization_id' => $organizationId, 'campaign_id' => $campaignId, 'case_id' => $caseId];
}

function loginAsAdminWithReviewCapabilities(string $email, array $capabilities): void
{
    registerAndLogin($email);
    grantFounderAccessForTests(accountForCampaignReviewTests($email), $capabilities);
    verifyRecentMfaForCampaignReviewTests();
}

it('lists a submitted campaign in the admin review queue', function (): void {
    ['case_id' => $caseId] = submitCampaignForReview('review-queue-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-queue-admin@wasplex.test', ['admin.campaign-reviews.view']);

    $cases = test()->getJson('/api/admin/campaign-reviews')->assertOk()->json('review_cases');

    expect(collect($cases)->pluck('id'))->toContain($caseId);
});

it('refuses access to the review queue without the capability', function (): void {
    loginAsAdminWithReviewCapabilities('review-nocap-admin@wasplex.test', []);

    test()->getJson('/api/admin/campaign-reviews')->assertStatus(403);
});

it('requires a reason for a correction request and keeps the reservation reserved', function (): void {
    ['campaign_id' => $campaignId, 'case_id' => $caseId] = submitCampaignForReview('review-changes-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-changes-admin@wasplex.test', ['admin.campaign-reviews.decide']);

    test()->postJson("/api/admin/campaign-reviews/{$caseId}/request-changes")->assertStatus(422);

    test()->postJson("/api/admin/campaign-reviews/{$caseId}/request-changes", ['reason' => 'La vidéo doit être verticale.'])
        ->assertOk()
        ->assertJsonPath('review_case.decision', 'changes_requested');

    expect(Campaign::query()->findOrFail($campaignId)->status)->toBe(Campaign::STATUS_CHANGES_REQUESTED);
    expect(CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->first()->status)
        ->toBe(CampaignBudgetReservation::STATUS_RESERVED);
});

it('lets the advertiser correct and resubmit without a second financing', function (): void {
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId, 'case_id' => $caseId] =
        submitCampaignForReview('review-resubmit-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-resubmit-admin@wasplex.test', ['admin.campaign-reviews.decide']);
    test()->postJson("/api/admin/campaign-reviews/{$caseId}/request-changes", ['reason' => 'Corrigez le titre.'])->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    loginOnlyForCampaignReviewTests('review-resubmit-advertiser@example.com');
    switchToAdvertiserSpaceForCampaignReviewTests($organizationId);

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'creative_configuration' => ['title' => 'Titre corrigé'],
    ])->assertOk();

    expect(CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->count())->toBe(1);

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/resubmit")
        ->assertOk()
        ->assertJsonPath('campaign.status', 'submitted');

    expect(CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->count())->toBe(1);
    expect(CampaignReviewCase::query()->where('campaign_id', $campaignId)->count())->toBe(2);
});

it('approves a campaign and keeps the reservation reserved', function (): void {
    ['campaign_id' => $campaignId, 'case_id' => $caseId] = submitCampaignForReview('review-approve-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-approve-admin@wasplex.test', ['admin.campaign-reviews.decide']);

    test()->postJson("/api/admin/campaign-reviews/{$caseId}/approve")
        ->assertOk()
        ->assertJsonPath('review_case.decision', 'approved');

    expect(Campaign::query()->findOrFail($campaignId)->status)->toBe(Campaign::STATUS_APPROVED);
    expect(CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->first()->status)
        ->toBe(CampaignBudgetReservation::STATUS_RESERVED);
});

it('never decides an already-decided review case twice', function (): void {
    ['case_id' => $caseId] = submitCampaignForReview('review-twice-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-twice-admin@wasplex.test', ['admin.campaign-reviews.decide']);

    test()->postJson("/api/admin/campaign-reviews/{$caseId}/approve")->assertOk();
    test()->postJson("/api/admin/campaign-reviews/{$caseId}/approve")->assertStatus(409);
});

it('rejects a campaign, releases the reservation exactly once, and blocks further edits', function (): void {
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId, 'case_id' => $caseId] =
        submitCampaignForReview('review-reject-advertiser@example.com', 100000);
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-reject-admin@wasplex.test', ['admin.campaign-reviews.decide']);

    $availableBefore = app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId);

    test()->postJson("/api/admin/campaign-reviews/{$caseId}/reject", ['reason' => 'Produit interdit.'])
        ->assertOk()
        ->assertJsonPath('review_case.decision', 'rejected');

    expect(Campaign::query()->findOrFail($campaignId)->status)->toBe(Campaign::STATUS_REJECTED);

    $reservation = CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->firstOrFail();
    expect($reservation->status)->toBe(CampaignBudgetReservation::STATUS_RELEASED);

    $availableAfter = app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId);
    expect($availableAfter)->toBe($availableBefore + $reservation->amount_minor);

    test()->postJson('/api/logout')->assertNoContent();
    loginOnlyForCampaignReviewTests('review-reject-advertiser@example.com');
    switchToAdvertiserSpaceForCampaignReviewTests($organizationId);
    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", ['objective_code' => 'faire_connaitre'])
        ->assertStatus(422);
});

it('suspends an approved campaign and keeps the reservation reserved', function (): void {
    ['campaign_id' => $campaignId, 'case_id' => $caseId] = submitCampaignForReview('review-suspend-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-suspend-admin@wasplex.test', ['admin.campaign-reviews.decide', 'admin.campaigns.suspend']);
    test()->postJson("/api/admin/campaign-reviews/{$caseId}/approve")->assertOk();

    test()->postJson("/api/admin/campaigns/{$campaignId}/suspend", ['reason' => 'Signalement utilisateur.'])
        ->assertOk()
        ->assertJsonPath('campaign.status', 'suspended');

    expect(CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->first()->status)
        ->toBe(CampaignBudgetReservation::STATUS_RESERVED);
});

it('refuses to suspend a campaign that is not approved', function (): void {
    ['campaign_id' => $campaignId] = submitCampaignForReview('review-suspend-invalid-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-suspend-invalid-admin@wasplex.test', ['admin.campaigns.suspend']);

    test()->postJson("/api/admin/campaigns/{$campaignId}/suspend", ['reason' => 'Test'])->assertStatus(422);
});

it('applies the optional distinct-decider separation only when configured', function (): void {
    config(['campaigns.review_require_distinct_decider' => true]);

    ['organization_id' => $organizationId, 'case_id' => $caseId, 'campaign_id' => $campaignId] =
        submitCampaignForReview('review-distinct-advertiser@example.com');
    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminWithReviewCapabilities('review-distinct-admin@wasplex.test', ['admin.campaign-reviews.decide']);
    test()->postJson("/api/admin/campaign-reviews/{$caseId}/request-changes", ['reason' => 'Corrigez le titre.'])->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    loginOnlyForCampaignReviewTests('review-distinct-advertiser@example.com');
    switchToAdvertiserSpaceForCampaignReviewTests($organizationId);
    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", ['creative_configuration' => ['title' => 'Corrigé']])->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/resubmit")->assertOk();
    $newCaseId = CampaignReviewCase::query()->where('campaign_id', $campaignId)->orderByDesc('opened_at')->orderByDesc('id')->firstOrFail()->id;
    test()->postJson('/api/logout')->assertNoContent();

    // Same admin who requested changes tries to decide the resubmission.
    loginOnlyForCampaignReviewTests('review-distinct-admin@wasplex.test');
    verifyRecentMfaForCampaignReviewTests();

    test()->postJson("/api/admin/campaign-reviews/{$newCaseId}/approve")->assertStatus(403);
});
