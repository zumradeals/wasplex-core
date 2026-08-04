<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Modules\AdvertiserStudio\Domain\Enums\AssetStatus;
use App\Modules\AdvertiserStudio\Domain\Enums\BrandStatus;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaign\Application\Services\CampaignPresenter;
use App\Modules\Campaign\Application\Services\CampaignService;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignQuote;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CampaignController
{
    public function __construct(
        private CampaignService $campaigns,
        private CampaignPresenter $presenter,
        private WalletCatalog $wallets,
        private WalletPresenter $walletPresenter,
    ) {}

    public function index(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $campaigns = Campaign::query()
            ->where('advertiser_space_id', $space->id)
            ->with($this->relations())
            ->latest()
            ->get();

        return Inertia::render('Studio/Campaigns', [
            ...$this->baseProps($request, $account, $space),
            'campaigns' => $campaigns->map(fn (Campaign $campaign): array => $this->presenter->campaign($campaign))->values(),
        ]);
    }

    public function createPage(Request $request): Response
    {
        [$account, $space] = $this->context($request);

        return $this->wizard($request, $account, $space);
    }

    public function store(Request $request): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate($this->rules());
        $campaign = $this->campaigns->create($space, $account, $data);

        return redirect()->route('studio.campaigns.edit', $campaign)->with('success', 'Le brouillon de campagne a été créé.');
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        [$account, $space] = $this->context($request);
        $campaign = $this->campaign($campaign, $space);
        $campaign->load($this->relations());

        return $this->wizard($request, $account, $space, $campaign);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $campaign = $this->campaign($campaign, $space);
        /** @var array<string, mixed> $data */
        $data = $request->validate($this->rules());
        $correction = $campaign->status === 'changes_requested';
        $this->campaigns->save($campaign, $space, $account, $data);

        return redirect()
            ->route('studio.campaigns.edit', $campaign)
            ->with('success', $correction ? 'La correction a été enregistrée. Vous pouvez resoumettre.' : 'Le brouillon a été enregistré.');
    }

    public function quote(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $campaign = $this->campaign($campaign, $space);
        $this->campaigns->quote($campaign, $space, $account);

        return redirect()->route('studio.campaigns.edit', $campaign)->with('success', 'Le devis a été généré et figé.');
    }

    public function fund(Request $request, Campaign $campaign, CampaignQuote $quote): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $campaign = $this->campaign($campaign, $space);
        $this->campaigns->fund(
            campaign: $campaign,
            quote: $quote,
            space: $space,
            actor: $account,
            traceId: $request->headers->get('X-Trace-Id'),
        );

        return redirect()->route('studio.campaigns.edit', $campaign)->with('success', 'Le budget a été réservé dans le Wallet annonceur.');
    }

    public function submit(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $campaign = $this->campaign($campaign, $space);
        $resubmission = $campaign->status === 'changes_requested';
        $this->campaigns->submit($campaign, $space, $account);

        return redirect()
            ->route('studio.campaigns.edit', $campaign)
            ->with('success', $resubmission
                ? 'La campagne corrigée a été resoumise pour revue administrative.'
                : 'La campagne a été soumise pour revue administrative.');
    }

    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $campaign = $this->campaign($campaign, $space);
        $this->campaigns->cancel($campaign, $space, $account, $request->headers->get('X-Trace-Id'));

        return redirect()->route('studio.campaigns.index')->with('success', 'La campagne a été annulée et sa réservation budgétaire libérée.');
    }

    private function wizard(Request $request, Account $account, UserSpace $space, ?Campaign $campaign = null): Response
    {
        $brands = Brand::query()
            ->where('advertiser_space_id', $space->id)
            ->where('status', BrandStatus::Active->value)
            ->with('activeVersion.logoAsset')
            ->orderBy('name')
            ->get();
        $assets = CreativeAsset::query()
            ->where('advertiser_space_id', $space->id)
            ->where('status', AssetStatus::Ready->value)
            ->with('versions')
            ->latest()
            ->get();
        $wallet = $this->wallets->forSpace($space);
        $baseProps = $this->baseProps($request, $account, $space);
        $campaignPayload = null;

        if ($campaign instanceof Campaign) {
            $campaignPayload = $this->presenter->campaign($campaign);
            if ($campaign->status === 'changes_requested') {
                $campaignPayload['status'] = 'draft';
                $case = $campaign->reviewCases->first(
                    fn (CampaignReviewCase $candidate): bool => $candidate->status === 'changes_requested',
                );
                if ($baseProps['flash']['success'] === null && $case instanceof CampaignReviewCase) {
                    $baseProps['flash']['success'] = 'Corrections demandées : '.(string) $case->decision_reason;
                }
            }
        }

        return Inertia::render('Studio/CampaignWizard', [
            ...$baseProps,
            'campaign' => $campaignPayload,
            'brands' => $brands->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slogan' => $brand->activeVersion?->slogan,
                'primaryColor' => $brand->activeVersion?->primary_color,
                'secondaryColor' => $brand->activeVersion?->secondary_color,
            ])->values(),
            'assets' => $assets->map(fn (CreativeAsset $asset): array => $this->presenter->asset($asset))->values(),
            'wallet' => $this->walletPresenter->wallet($wallet),
            'economicClasses' => [
                ['code' => 'FREE', 'label' => 'Gratuit'],
                ['code' => 'PREMIUM', 'label' => 'Premium'],
                ['code' => 'GOLD', 'label' => 'Gold'],
                ['code' => 'PLATINUM', 'label' => 'Platine'],
            ],
            'objectives' => [
                ['code' => 'notoriety', 'label' => 'Faire connaître ma marque'],
                ['code' => 'traffic', 'label' => 'Attirer des visiteurs'],
                ['code' => 'conversion', 'label' => 'Obtenir des demandes ou ventes'],
                ['code' => 'engagement', 'label' => 'Créer de l’engagement'],
            ],
            'minimumBudgetMinor' => (int) config('campaign.minimum_budget_minor', 5000),
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'brand_id' => ['required', 'ulid'],
            'objective' => ['required', 'string', 'in:notoriety,traffic,conversion,engagement'],
            'headline' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:2000'],
            'call_to_action' => ['nullable', 'string', 'max:80'],
            'destination_url' => ['nullable', 'url:http,https', 'max:1000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'territory_name' => ['nullable', 'string', 'max:160'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:100'],
            'selected_classes' => ['array', 'max:4'],
            'selected_classes.*' => ['string', 'in:FREE,PREMIUM,GOLD,PLATINUM'],
            'budget_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'creative_asset_ids' => ['array', 'max:10'],
            'creative_asset_ids.*' => ['ulid'],
            'current_step' => ['nullable', 'integer', 'min:1', 'max:7'],
        ];
    }

    /** @return array{0:Account,1:UserSpace} */
    private function context(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::Advertiser, 403);
        $space->loadMissing(['organization', 'memberships']);

        return [$account, $space];
    }

    /** @return array<string, mixed> */
    private function baseProps(Request $request, Account $account, UserSpace $activeSpace): array
    {
        $account->loadMissing(['profile', 'spaces.organization']);

        return [
            'account' => [
                'id' => $account->id,
                'displayName' => $account->profile?->display_name,
            ],
            'activeSpace' => [
                'id' => $activeSpace->id,
                'kind' => $activeSpace->kind->value,
                'label' => $activeSpace->label,
            ],
            'spaces' => $account->spaces->map(fn (UserSpace $candidate): array => [
                'id' => $candidate->id,
                'kind' => $candidate->kind->value,
                'label' => $candidate->label,
                'active' => $activeSpace->id === $candidate->id,
            ])->values(),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }

    private function campaign(Campaign $campaign, UserSpace $space): Campaign
    {
        abort_unless($campaign->advertiser_space_id === $space->id, 404);

        return $campaign;
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'brand.activeVersion.logoAsset',
            'activeVersion.creatives.versions',
            'audiences',
            'quotes.audience',
            'quotes.priceCatalog',
            'funding.budgetReservation.walletReservation',
            'funding.wallet.projection',
            'reviewCases.submitter.profile',
            'reviewCases.decider.profile',
            'reviewCases.task',
            'reviewCases.events.actor.profile',
            'statusEvents.actor.profile',
        ];
    }
}
