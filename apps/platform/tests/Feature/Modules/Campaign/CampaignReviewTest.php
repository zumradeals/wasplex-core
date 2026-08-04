<?php

namespace Tests\Feature\Modules\Campaign;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaign\Application\Services\CampaignReviewService;
use App\Modules\Campaign\Application\Services\CampaignService;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewEvent;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewTask;
use App\Modules\Campaign\Infrastructure\Models\CampaignStatusEvent;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CampaignReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('advertiser-studio.disk', 'public');
        config()->set('campaign.sandbox_cpm_minor', 1000);
        config()->set('campaign.minimum_budget_minor', 5000);
        config()->set('campaign-review.require_distinct_decider', false);
    }

    public function test_request_changes_preserves_reservation_and_resubmission_opens_a_new_case(): void
    {
        [$advertiser, $space, $campaign, $wallet, $brand, $asset] = $this->submittedCampaign();
        $reviewer = $this->account();
        $reviews = app(CampaignReviewService::class);
        $campaigns = app(CampaignService::class);

        $changed = $reviews->requestChanges(
            $campaign,
            $reviewer,
            'Remplacer le visuel et clarifier le message de destination.',
        );

        expect($changed->status)->toBe('changes_requested')
            ->and(CampaignReviewCase::query()->where('status', 'changes_requested')->count())->toBe(1)
            ->and(CampaignReviewTask::query()->where('status', 'completed')->count())->toBe(1)
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(65000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(35000);

        $corrected = $campaigns->save($changed, $space, $advertiser, [
            ...$this->campaignData($brand, $asset),
            'headline' => 'Autodesk officiel pour les professionnels',
            'body' => 'Offre clarifiée après la revue administrative.',
            'budget_minor' => 35000,
            'current_step' => 7,
        ]);
        $resubmitted = $campaigns->submit($corrected, $space, $advertiser);

        expect($resubmitted->status)->toBe('submitted')
            ->and(CampaignVersion::query()->where('campaign_id', $campaign->id)->count())->toBe(2)
            ->and(CampaignReviewCase::query()->where('campaign_id', $campaign->id)->count())->toBe(2)
            ->and(CampaignReviewCase::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->count())->toBe(1)
            ->and(CampaignReviewEvent::query()->where('type', 'campaign_resubmitted')->count())->toBe(1)
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(65000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(35000);
    }

    public function test_approval_preserves_the_reserved_budget_and_marks_campaign_eligible(): void
    {
        [, , $campaign, $wallet] = $this->submittedCampaign();
        $reviewer = $this->account();

        $approved = app(CampaignReviewService::class)->approve(
            $campaign,
            $reviewer,
            'Contenu, ciblage et lien conformes.',
        );

        expect($approved->status)->toBe('approved')
            ->and($approved->funding?->status)->toBe('approved')
            ->and($approved->funding?->budgetReservation?->status)->toBe('active')
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(65000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(35000)
            ->and(CampaignStatusEvent::query()->where('to_status', 'approved')->count())->toBe(1);
    }

    public function test_rejection_releases_the_reservation_once_and_blocks_future_eligibility(): void
    {
        [, , $campaign, $wallet] = $this->submittedCampaign();
        $reviewer = $this->account();
        $reviews = app(CampaignReviewService::class);

        $rejected = $reviews->reject(
            $campaign,
            $reviewer,
            'Le média contient une promesse commerciale non vérifiable.',
        );
        $replayed = $reviews->reject(
            $rejected->refresh(),
            $reviewer,
            'Le média contient une promesse commerciale non vérifiable.',
        );

        expect($rejected->status)->toBe('rejected')
            ->and($replayed->status)->toBe('rejected')
            ->and($rejected->funding?->status)->toBe('released')
            ->and($rejected->funding?->budgetReservation?->status)->toBe('released')
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(100000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(0)
            ->and(CampaignStatusEvent::query()->where('to_status', 'rejected')->count())->toBe(1);
    }

    public function test_suspension_preserves_the_reservation_after_approval(): void
    {
        [, , $campaign, $wallet] = $this->submittedCampaign();
        $reviewer = $this->account();
        $reviews = app(CampaignReviewService::class);
        $approved = $reviews->approve($campaign, $reviewer);

        $suspended = $reviews->suspend(
            $approved,
            $reviewer,
            'Suspension préventive avant ouverture du Feed.',
        );

        expect($suspended->status)->toBe('suspended')
            ->and($suspended->funding?->status)->toBe('suspended')
            ->and($suspended->funding?->budgetReservation?->status)->toBe('active')
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(65000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(35000)
            ->and(CampaignStatusEvent::query()->where('to_status', 'suspended')->count())->toBe(1);
    }

    public function test_distinct_decider_policy_can_refuse_the_submitter(): void
    {
        [$advertiser, , $campaign] = $this->submittedCampaign();
        config()->set('campaign-review.require_distinct_decider', true);

        $this->expectException(ValidationException::class);
        app(CampaignReviewService::class)->approve($campaign, $advertiser);
    }

    public function test_bootstrap_grants_review_capabilities_and_backfills_a_missing_case(): void
    {
        [, , $campaign] = $this->submittedCampaign();
        CampaignReviewCase::query()->delete();
        $administrator = $this->account();
        $administrationSpace = UserSpace::query()->create([
            'account_id' => $administrator->id,
            'organization_id' => null,
            'kind' => SpaceKind::Administration,
            'label' => 'Administration Wasplex',
            'status' => 'active',
        ]);

        $this->artisan('campaign-review:bootstrap')->assertSuccessful();

        expect(CampaignReviewCase::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->count())->toBe(1)
            ->and(CapabilityGrant::query()
                ->where('account_id', $administrator->id)
                ->where('space_id', $administrationSpace->id)
                ->where('capability', 'campaign.review.approve')
                ->whereNull('revoked_at')
                ->exists())->toBeTrue();
    }

    /** @return array{0:Account,1:UserSpace,2:Campaign,3:Wallet,4:Brand,5:CreativeAsset} */
    private function submittedCampaign(): array
    {
        [$account, $space, $brand, $asset] = $this->studio('GamaDeals');
        $wallet = app(WalletCatalog::class)->forSpace($space);
        $this->creditWallet($wallet, 100000);
        $service = app(CampaignService::class);
        $campaign = $service->create($space, $account, $this->campaignData($brand, $asset));
        $quote = $service->quote($campaign, $space, $account);
        $service->fund($campaign, $quote, $space, $account);
        $submitted = $service->submit($campaign->refresh(), $space, $account);

        return [$account, $space, $submitted, $wallet, $brand, $asset];
    }

    /** @return array{0:Account,1:UserSpace,2:Brand,3:CreativeAsset} */
    private function studio(string $name): array
    {
        $account = $this->account();
        $organization = Organization::query()->create([
            'created_by_account_id' => $account->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.substr($account->id, -6),
            'kind' => 'advertiser',
            'status' => 'active',
        ]);
        $space = UserSpace::query()->create([
            'account_id' => $account->id,
            'organization_id' => $organization->id,
            'kind' => SpaceKind::Advertiser,
            'label' => $name,
            'status' => 'active',
        ])->load('organization');
        $asset = app(CreativeAssetService::class)->store(
            $space,
            $account,
            UploadedFile::fake()->create('campagne.png', 200, 'image/png'),
            null,
            ['label' => "Contenu {$name}", 'usage_context' => 'campaign'],
        );
        $brand = app(BrandService::class)->create($space, $account, [
            'public_name' => $name,
            'slogan' => 'La marque de test',
            'description' => 'Identité publicitaire de test.',
            'primary_color' => '#0EA5E9',
            'secondary_color' => '#F97316',
            'logo_asset_id' => $asset->id,
        ]);

        return [$account, $space, $brand, $asset];
    }

    private function account(): Account
    {
        return Account::query()->create([
            'status' => AccountStatus::Active,
            'password_hash' => str_repeat('x', 60),
            'locale' => 'fr',
            'timezone' => 'Africa/Abidjan',
        ]);
    }

    /** @return array<string, mixed> */
    private function campaignData(Brand $brand, CreativeAsset $asset): array
    {
        return [
            'name' => 'Autodesk Abidjan',
            'brand_id' => $brand->id,
            'objective' => 'conversion',
            'headline' => 'Autodesk 3 ans',
            'body' => 'Une offre destinée aux professionnels.',
            'call_to_action' => 'Découvrir',
            'destination_url' => 'https://example.test/autodesk',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-17',
            'territory_name' => 'Abidjan',
            'radius_km' => 40,
            'selected_classes' => ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM'],
            'budget_minor' => 35000,
            'creative_asset_ids' => [$asset->id],
            'current_step' => 5,
        ];
    }

    private function creditWallet(Wallet $wallet, int $amount): void
    {
        app('Illuminate\Contracts\Console\Kernel')->call('ledger:bootstrap-core');
        $clearing = app(WalletCatalog::class)->clearingAccountId();
        $posted = app(LedgerContract::class)->post(new PostingRequest(
            journalCode: 'WALLET',
            type: 'TEST_CAMPAIGN_REVIEW_WALLET_CREDIT',
            sourceModule: 'campaign-review-tests',
            businessReference: "campaign-review-wallet-credit:{$wallet->id}",
            idempotencyKey: "campaign-review-wallet-credit:{$wallet->id}:{$amount}",
            unit: 'WP',
            currency: 'XOF',
            entries: [
                new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test P007'),
                new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet P007'),
            ],
        ));
        app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
    }
}
