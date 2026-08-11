<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Campaigns\Infrastructure\Models\CampaignPublicVisit;
use App\Modules\Campaigns\Infrastructure\Models\CampaignVersion;
use App\Modules\Feed\Infrastructure\Models\FeedCampaignReward;

function publicCampaignForDistributionTests(
    string $slug,
    string $mode = Campaign::DISTRIBUTION_MEMBERS_PUBLIC,
    string $status = Campaign::STATUS_APPROVED,
): Campaign {
    $campaign = Campaign::query()->create([
        'organization_id' => 'public-test-org',
        'brand_id' => 'public-test-brand',
        'type' => Campaign::TYPE_FAST,
        'objective_code' => 'faire_connaitre',
        'status' => $status,
        'distribution_mode' => $mode,
        'public_slug' => $slug,
        'currency' => 'XOF',
        'created_by' => 'public-test-actor',
    ]);

    CampaignVersion::query()->create([
        'campaign_id' => $campaign->id,
        'version_number' => 1,
        'creative_configuration' => ['title' => 'Campagne publique de test'],
        'status' => CampaignVersion::STATUS_SUBMITTED,
    ]);

    return $campaign->refresh();
}

it('keeps campaigns private by default and never exposes a members-only campaign', function (): void {
    $campaign = publicCampaignForDistributionTests(
        'members-only-test',
        Campaign::DISTRIBUTION_MEMBERS_ONLY,
    );

    expect($campaign->distribution_mode)->toBe(Campaign::DISTRIBUTION_MEMBERS_ONLY);

    test()->get('/c/members-only-test')->assertNotFound();
    test()->postJson('/api/public/campaigns/members-only-test/visits', [
        'visitor_token' => 'visitor-token-0000000001',
        'visit_token' => 'visit-token-000000000001',
    ])->assertNotFound();
});

it('serves an approved public campaign without authentication and measures only public exposure', function (): void {
    $campaign = publicCampaignForDistributionTests('public-campaign-test');

    $rewardCount = FeedCampaignReward::query()->count();
    $envelopeCount = CampaignEnvelopeConsumption::query()->count();

    test()->get('/c/public-campaign-test')->assertOk();

    $first = test()->postJson('/api/public/campaigns/public-campaign-test/visits', [
        'visitor_token' => 'visitor-token-same-person-0001',
        'visit_token' => 'visit-token-session-00000001',
    ])->assertCreated();

    $visitId = $first->json('visit_id');

    // Même chargement = même visite, pas un compteur artificiellement doublé.
    test()->postJson('/api/public/campaigns/public-campaign-test/visits', [
        'visitor_token' => 'visitor-token-same-person-0001',
        'visit_token' => 'visit-token-session-00000001',
    ])->assertCreated()->assertJsonPath('visit_id', $visitId);

    test()->postJson("/api/public/campaigns/public-campaign-test/visits/{$visitId}/complete")->assertOk();
    test()->postJson("/api/public/campaigns/public-campaign-test/visits/{$visitId}/complete")->assertOk();
    test()->postJson("/api/public/campaigns/public-campaign-test/visits/{$visitId}/share")->assertOk();
    test()->postJson("/api/public/campaigns/public-campaign-test/visits/{$visitId}/join")->assertOk();

    $visit = CampaignPublicVisit::query()->findOrFail($visitId);
    expect($visit->campaign_id)->toBe($campaign->id);
    expect($visit->visitor_hash)->not->toBe('visitor-token-same-person-0001');
    expect($visit->visit_hash)->not->toBe('visit-token-session-00000001');
    expect($visit->completed_at)->not->toBeNull();
    expect($visit->share_count)->toBe(1);

    // La couche publique n'ouvre jamais un droit à récompense et ne réserve
    // aucune nouvelle part du budget publicitaire.
    expect(FeedCampaignReward::query()->count())->toBe($rewardCount);
    expect(CampaignEnvelopeConsumption::query()->count())->toBe($envelopeCount);
});

it('counts repeat public visits separately while keeping approximate visitors pseudonymous', function (): void {
    $campaign = publicCampaignForDistributionTests('public-reach-test');

    $one = test()->postJson('/api/public/campaigns/public-reach-test/visits', [
        'visitor_token' => 'visitor-token-repeat-person-01',
        'visit_token' => 'visit-token-repeat-session-01',
    ])->assertCreated()->json('visit_id');

    $two = test()->postJson('/api/public/campaigns/public-reach-test/visits', [
        'visitor_token' => 'visitor-token-repeat-person-01',
        'visit_token' => 'visit-token-repeat-session-02',
    ])->assertCreated()->json('visit_id');

    expect($two)->not->toBe($one);
    expect(CampaignPublicVisit::query()->where('campaign_id', $campaign->id)->count())->toBe(2);
    expect(CampaignPublicVisit::query()->where('campaign_id', $campaign->id)->distinct()->count('visitor_hash'))->toBe(1);
});

it('stops public delivery immediately when a campaign is suspended', function (): void {
    publicCampaignForDistributionTests(
        'public-suspended-test',
        Campaign::DISTRIBUTION_MEMBERS_PUBLIC,
        Campaign::STATUS_SUSPENDED,
    );

    test()->get('/c/public-suspended-test')->assertNotFound();
    test()->postJson('/api/public/campaigns/public-suspended-test/visits', [
        'visitor_token' => 'visitor-token-suspended-0001',
        'visit_token' => 'visit-token-suspended-000001',
    ])->assertNotFound();
});
