<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Application\Services\AdvertisingExplanationService;
use App\Modules\Advertising\Application\Services\AdvertisingSegmentService;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegment;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AdvertisingMatchingController
{
    public function __construct(
        private AdvertisingSegmentService $segments,
        private AdvertisingExplanationService $explanations,
    ) {}

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

    public function configure(Request $request, Campaign $campaign): JsonResponse
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

        return response()->json([
            'data' => $this->presentSegment($segment),
        ], 201);
    }

    public function estimate(Request $request, Campaign $campaign): JsonResponse
    {
        [$account, $space] = $this->advertiserContext($request);
        $this->guardCampaign($campaign, $space);
        $segment = $this->segments->activeForCampaign($campaign);
        abort_unless($segment instanceof AdvertisingSegment, 422, 'Configurez le segment avant de demander une estimation.');
        $estimate = $this->segments->estimate($segment, $account);

        return response()->json([
            'data' => $this->segments->presentEstimate($estimate),
        ]);
    }

    public function explanation(Request $request, AdvertisingMatch $match): JsonResponse
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::User, 403);

        return response()->json([
            'data' => $this->explanations->explain($account, $match),
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

    private function guardCampaign(Campaign $campaign, UserSpace $space): void
    {
        abort_unless($campaign->advertiser_space_id === $space->id, 404);
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
            'rules' => $segment->rules->map(static fn ($rule): array => [
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
