<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\Campaigns\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaigns\Infrastructure\Models\CampaignQuote;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    // 6 synthetic subscribers are created per test below; lower the
    // real default (20) so quoting isn't blocked by "segment trop
    // petit" in tests that aren't specifically exercising that rule.
    config(['campaigns.minimum_segment_size' => 2]);
});

/**
 * Credits the advertiser Wallet directly through the real Ledger contract
 * (not the GeniusPay/supervised-deposit flow, which is P003's own concern)
 * — sole purpose here is giving a campaign test organization a real,
 * available balance to fund against.
 */
function creditAdvertiserWalletForTests(string $organizationId, int $amountMinor): void
{
    $posting = app(LedgerPostingContract::class);
    $organizationAccount = LedgerAccountReference::forOrganization(
        AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
        $organizationId,
        'LIABILITY_ADVERTISER',
        'WP',
    );
    $clearing = LedgerAccountReference::system('wasplex.cash.clearing', 'ASSET', 'WP');

    $posting->post(new PostLedgerTransaction(
        type: 'TEST_ADVERTISER_CREDIT',
        sourceModule: 'test',
        idempotencyKey: 'test-credit:'.$organizationId.':'.uniqid(),
        entries: [
            LedgerEntryInput::debit($clearing, Money::of($amountMinor, 'WP')),
            LedgerEntryInput::credit($organizationAccount, Money::of($amountMinor, 'WP')),
        ],
    ));
}

function setUpQuotableCampaign(string $email, int $budgetAmountMinor = 100000): array
{
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('PLATINUM', 'CI');
    createActiveSubscriberInClass('PLATINUM', 'CI');
    createActiveSubscriberInClass('PLATINUM', 'CI');

    registerAndLogin($email);
    $organizationId = createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'audience_configuration' => ['economic_classes' => ['GOLD', 'PLATINUM'], 'territory' => ['country_code' => 'CI']],
        'budget_configuration' => ['budget_amount_minor' => $budgetAmountMinor],
    ])->assertOk();

    return ['organization_id' => $organizationId, 'campaign_id' => $campaignId];
}

it('blocks quoting without a published price catalog', function (): void {
    ['campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-quote-1@example.com');

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertStatus(422);
});

it('blocks quoting when the targeted segment is too small', function (): void {
    publishPriceCatalog();
    registerAndLogin('campaign-quote-2@example.com');
    createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    // No subscribers created at all in this test -> segment size 0.
    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'audience_configuration' => ['economic_classes' => ['GOLD']],
        'budget_configuration' => ['budget_amount_minor' => 100000],
    ])->assertOk();

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertStatus(422);
});

it('produces a quote with an exact 50/50 split and normalized class weights', function (): void {
    publishPriceCatalog(basePriceMinorPerEvent: 500);
    ['campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-quote-3@example.com', 100000);

    $response = test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();
    $quoteId = CampaignQuote::query()->latest('created_at')->firstOrFail()->id;
    $quote = CampaignQuote::query()->findOrFail($quoteId);

    expect($response->json('campaign.status'))->toBe('quoted');
    expect($quote->gross_amount_minor)->toBe(100000);
    expect($quote->net_distributable_amount_minor)->toBe(100000);

    // GOLD 35 / (35+35) = 50%, PLATINUM the other 50% — user envelope is
    // 50000, split evenly between the two selected classes.
    $breakdown = $quote->class_breakdown;
    expect($breakdown['GOLD']['envelope_minor'] + $breakdown['PLATINUM']['envelope_minor'])->toBe(50000);
    expect($breakdown['GOLD']['envelope_minor'])->toBe(25000);
    expect($breakdown['PLATINUM']['envelope_minor'])->toBe(25000);

    // No fraction silently lost: gain × events + reliquat == envelope, per class.
    foreach (['GOLD', 'PLATINUM'] as $code) {
        $class = $breakdown[$code];
        expect(($class['gain_unitaire_minor'] * $class['events']) + $class['reliquat_minor'])->toBe($class['envelope_minor']);
    }

    expect($quote->estimated_events)->toBeGreaterThan(0);
});

it('refuses funding with insufficient advertiser balance', function (): void {
    publishPriceCatalog();
    ['campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-fund-1@example.com', 100000);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();

    // No wallet credit at all -> balance 0.
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")
        ->assertStatus(422)
        ->assertJsonPath('available_minor', 0);
});

it('funds a campaign and reserves the exact budget on the Grand Livre', function (): void {
    publishPriceCatalog();
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-fund-2@example.com', 100000);
    creditAdvertiserWalletForTests($organizationId, 200000);

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")
        ->assertOk()
        ->assertJsonPath('campaign.status', 'funded');

    expect(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(100000);

    $reservation = CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->firstOrFail();
    expect($reservation->amount_minor)->toBe(100000);
    expect($reservation->status)->toBe(CampaignBudgetReservation::STATUS_RESERVED);
});

it('is idempotent when funding is requested twice', function (): void {
    publishPriceCatalog();
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-fund-3@example.com', 100000);
    creditAdvertiserWalletForTests($organizationId, 200000);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertOk();

    expect(CampaignBudgetReservation::query()->where('campaign_id', $campaignId)->count())->toBe(1);
    expect(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(100000);
});

it('refuses to fund an expired quote', function (): void {
    publishPriceCatalog();
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-fund-4@example.com', 100000);
    creditAdvertiserWalletForTests($organizationId, 200000);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();

    CampaignQuote::query()->latest('created_at')->firstOrFail()->update(['expires_at' => now()->subHour()]);

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertStatus(422);
});

it('requires funding before submission', function (): void {
    publishPriceCatalog();
    ['campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-submit-1@example.com', 100000);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/submit")->assertStatus(422);
});

it('submits a funded campaign and refuses further edits', function (): void {
    publishPriceCatalog();
    ['organization_id' => $organizationId, 'campaign_id' => $campaignId] = setUpQuotableCampaign('campaign-submit-2@example.com', 100000);
    creditAdvertiserWalletForTests($organizationId, 200000);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertOk();

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/submit")
        ->assertOk()
        ->assertJsonPath('campaign.status', 'submitted');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", ['objective_code' => 'faire_connaitre'])
        ->assertStatus(422);
});
