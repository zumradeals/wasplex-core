<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Application\Services\AdvertisingExplanationService;
use App\Modules\Advertising\Application\Services\AdvertisingSegmentService;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegment;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegmentEstimate;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegmentRule;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdvertisingMatchingController
{
    public function __construct(
        private AdvertisingSegmentService $segments,
        private AdvertisingExplanationService $explanations,
    ) {}

    public function page(Request $request, Campaign $campaign): Response
    {
        [$account, $space] = $this->advertiserContext($request);
        $this->guardCampaign($campaign, $space);
        $campaign->loadMissing(['brand', 'activeVersion']);
        $segment = $this->segments->activeForCampaign($campaign);
        $segment?->loadMissing(['rules.taxonomy', 'estimates']);
        $estimate = $segment?->estimates->first();

        return Inertia::render('Studio/CampaignTargeting', [
            ...$this->baseProps($request, $account, $space),
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'brand' => [
                    'id' => $campaign->brand->id,
                    'name' => $campaign->brand->name,
                ],
                'version' => [
                    'territoryName' => $campaign->activeVersion?->territory_name,
                    'radiusKm' => $campaign->activeVersion?->radius_km,
                    'selectedClasses' => $campaign->activeVersion?->selected_classes ?? [],
                ],
            ],
            'taxonomies' => $this->segments->allowedTaxonomies(),
            'segment' => $segment instanceof AdvertisingSegment
                ? $this->presentSegment($segment)
                : null,
            'estimate' => $estimate instanceof AdvertisingSegmentEstimate
                ? $this->segments->presentEstimate($estimate)
                : null,
            'privacy' => [
                'membersExported' => false,
                'minimumSegmentSize' => max(1, (int) config('advertising.minimum_segment_size', 25)),
                'roundingStep' => max(1, (int) config('advertising.estimate_rounding_step', 10)),
            ],
        ]);
    }

    public function taxonomies(Request $request): JsonResponse
    {
        $this->advertiserContext($request);

        return response()->json([
            'data' => $this->segments->allowedTaxonomies(),
            'privacy' => [
                'membersExported' => false,
                'minimumSegmentSize' => max(1, (int) config('advertising.minimum_segment_size', 25)),
            ],
        ]);
    }

    public function configure(Request $request, Campaign $campaign): JsonResponse|RedirectResponse
    {
        [$account, $space] = $this->advertiserContext($request);
        $this->guardCampaign($campaign, $space);
        /** @var array{rules:array<int, array{taxonomy_code:string,operator?:string,values:array<int, string>,required?:bool}>} $data */
        $data = $request->validate([
            'rules' => ['required', 'array', 'min:1', 'max:10'],
            'rules.*.taxonomy_code' => ['required', 'string', 'max:180'],
            'rules.*.operator' => ['nullable', 'string', 'in:equals,in'],
            'rules.*.values' => ['required', 'array', 'min:1', 'max:20'],
            'rules.*.values.*' => ['required', 'string', 'max:160'],
            'rules.*.required' => ['nullable', 'boolean'],
        ]);
        $segment = $this->segments->configure($campaign, $account, $data['rules']);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->presentSegment($segment),
            ], 201);
        }

        return redirect()
            ->route('advertising.targeting.page', $campaign)
            ->with('success', 'Le segment protégé a été enregistré. Lancez maintenant son estimation.');
    }

    public function estimate(Request $request, Campaign $campaign): JsonResponse|RedirectResponse
    {
        [$account, $space] = $this->advertiserContext($request);
        $this->guardCampaign($campaign, $space);
        $segment = $this->segments->activeForCampaign($campaign);
        abort_unless($segment instanceof AdvertisingSegment, 422, 'Configurez le segment avant de demander une estimation.');
        $estimate = $this->segments->estimate($segment, $account);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->segments->presentEstimate($estimate),
            ]);
        }

        return redirect()
            ->route('advertising.targeting.page', $campaign)
            ->with(
                'success',
                $estimate->status === 'available'
                    ? 'L’estimation protégée du segment est disponible.'
                    : 'Le segment est sous le seuil de confidentialité. Aucun volume précis ni membre ne sera affiché.',
            );
    }

    public function explanation(Request $request, AdvertisingMatch $match): JsonResponse|Response
    {
        [$account, $space] = $this->userContext($request);
        $explanation = $this->explanations->explain($account, $match);

        if ($request->expectsJson()) {
            return response()->json(['data' => $explanation]);
        }

        $match->loadMissing(['campaign.brand']);

        return Inertia::render('User/AdvertisingExplanation', [
            ...$this->baseProps($request, $account, $space),
            'explanation' => $explanation,
            'campaign' => [
                'id' => $match->campaign->id,
                'name' => $match->campaign->name,
                'brand' => $match->campaign->brand->name,
            ],
        ]);
    }

    /** @return array{0:Account,1:UserSpace} */
    private function advertiserContext(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::Advertiser, 403);

        return [$account, $space];
    }

    /** @return array{0:Account,1:UserSpace} */
    private function userContext(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::User, 403);

        return [$account, $space];
    }

    private function guardCampaign(Campaign $campaign, UserSpace $space): void
    {
        abort_unless($campaign->advertiser_space_id === $space->id, 404);
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

    /** @return array<string, mixed> */
    private function presentSegment(AdvertisingSegment $segment): array
    {
        return [
            'id' => $segment->id,
            'campaignId' => $segment->campaign_id,
            'campaignVersionId' => $segment->campaign_version_id,
            'version' => $segment->version,
            'status' => $segment->status,
            'ruleVersion' => $segment->rule_version,
            'minimumSize' => $segment->minimum_size,
            'rules' => $segment->rules->map(static fn (AdvertisingSegmentRule $rule): array => [
                'taxonomyCode' => $rule->taxonomy->code,
                'taxonomyLabel' => $rule->taxonomy->label,
                'operator' => $rule->operator,
                'values' => $rule->expected_values,
                'required' => $rule->required,
            ])->values()->all(),
            'membersExported' => false,
        ];
    }
}
