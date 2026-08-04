<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Modules\Campaign\Application\Services\CampaignReviewPresenter;
use App\Modules\Campaign\Application\Services\CampaignReviewService;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CampaignReviewAdminController
{
    public function __construct(
        private CampaignReviewService $reviews,
        private CampaignReviewPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $filter = (string) $request->query('status', 'pending');
        abort_unless(in_array($filter, ['pending', 'approved', 'rejected', 'suspended', 'all'], true), 404);

        $query = Campaign::query()
            ->with($this->relations())
            ->whereIn('status', ['submitted', 'approved', 'rejected', 'suspended', 'changes_requested']);

        match ($filter) {
            'pending' => $query->where('status', 'submitted')->whereHas(
                'reviewCases',
                fn ($reviewQuery) => $reviewQuery->where('status', 'pending'),
            ),
            'approved' => $query->where('status', 'approved'),
            'rejected' => $query->where('status', 'rejected'),
            'suspended' => $query->where('status', 'suspended'),
            default => null,
        };

        $campaigns = $query
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")
            ->latest('submitted_at')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/CampaignReviews', [
            ...$this->baseProps($request, $account, $space),
            'campaigns' => $campaigns
                ->map(fn (Campaign $campaign): array => $this->presenter->summary($campaign))
                ->values(),
            'filter' => $filter,
            'counts' => [
                'pending' => $this->reviews->pendingCount(),
                'approved' => Campaign::query()->where('status', 'approved')->count(),
                'rejected' => Campaign::query()->where('status', 'rejected')->count(),
                'suspended' => Campaign::query()->where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        [$account, $space] = $this->context($request);
        $campaign->load($this->relations());

        return Inertia::render('Admin/CampaignReviewShow', [
            ...$this->baseProps($request, $account, $space),
            'campaign' => $this->presenter->detail($campaign),
        ]);
    }

    public function requestChanges(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account] = $this->context($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $this->reviews->requestChanges($campaign, $account, (string) $validated['reason']);

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'La campagne a été renvoyée à l’annonceur avec les corrections demandées.');
    }

    public function approve(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account] = $this->context($request);
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:4000'],
        ]);
        $this->reviews->approve($campaign, $account, $validated['reason'] ?? null);

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'La campagne est approuvée et devient éligible au futur Matching.');
    }

    public function reject(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account] = $this->context($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $this->reviews->reject(
            campaign: $campaign,
            actor: $account,
            reason: (string) $validated['reason'],
            traceId: $request->headers->get('X-Trace-Id'),
        );

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'La campagne a été rejetée et sa réservation budgétaire libérée.');
    }

    public function suspend(Request $request, Campaign $campaign): RedirectResponse
    {
        [$account] = $this->context($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $this->reviews->suspend($campaign, $account, (string) $validated['reason']);

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'La campagne est suspendue. Sa réservation reste protégée.');
    }

    /** @return array{0:Account,1:UserSpace} */
    private function context(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::Administration, 403);

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

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'organization',
            'space',
            'creator.profile',
            'brand.activeVersion.logoAsset',
            'activeVersion.creatives.versions',
            'audiences',
            'quotes.audience',
            'quotes.priceCatalog',
            'funding.budgetReservation.walletReservation',
            'reviewCases.submitter.profile',
            'reviewCases.decider.profile',
            'reviewCases.task',
            'reviewCases.events.actor.profile',
            'statusEvents.actor.profile',
        ];
    }
}
