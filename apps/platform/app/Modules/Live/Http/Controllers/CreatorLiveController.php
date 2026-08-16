<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Live\Application\Services\LiveLifecycleService;
use App\Modules\Live\Application\Services\LivePresenter;
use App\Modules\Live\Application\Services\LiveRewardSeatService;
use App\Modules\Live\Application\Services\LiveSponsorshipService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreatorLiveController
{
    public function __construct(
        private readonly LiveLifecycleService $lifecycle,
        private readonly LiveSponsorshipService $sponsorship,
        private readonly LiveRewardSeatService $rewardSeats,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $organizationId = $this->advertiserOrganizationId($request);
        $lives = LiveEvent::query()
            ->with(['owner.profile', 'advertiserOrganization'])
            ->where('advertiser_organization_id', $organizationId)
            ->where('owner_account_id', $accountId)
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (LiveEvent $live): array => LivePresenter::live($live, $accountId, true))
            ->values();

        return response()->json(['lives' => $lives]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(false));
        $live = $this->lifecycle->create(
            $request->user(),
            $this->advertiserOrganizationId($request),
            $data,
        );

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)], 201);
    }

    public function update(Request $request, LiveEvent $live): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $live = $this->lifecycle->update(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
            $data,
        );

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)]);
    }

    public function schedule(Request $request, LiveEvent $live): JsonResponse
    {
        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);
        $this->sponsorship->assertMayScheduleOrStart(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );
        $scheduledAt = CarbonImmutable::parse((string) $data['scheduled_at']);
        $live = $this->lifecycle->schedule(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
            $scheduledAt,
        );

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)]);
    }

    public function start(Request $request, LiveEvent $live): JsonResponse
    {
        $this->sponsorship->assertMayScheduleOrStart(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );
        $live = $this->lifecycle->start(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)]);
    }

    public function pause(Request $request, LiveEvent $live): JsonResponse
    {
        $live = $this->lifecycle->pause(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)]);
    }

    public function resume(Request $request, LiveEvent $live): JsonResponse
    {
        $live = $this->lifecycle->resume(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)]);
    }

    public function end(Request $request, LiveEvent $live): JsonResponse
    {
        $live = $this->lifecycle->end(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );
        $this->rewardSeats->closeLive($live);

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id, true)]);
    }

    private function advertiserOrganizationId(Request $request): string
    {
        return (string) $request->attributes->get('advertiser_organization_id');
    }

    private function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:140'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'string', 'max:60'],
            'language' => ['sometimes', 'string', 'max:10'],
            'visibility' => ['sometimes', Rule::in(['public', 'unlisted'])],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'planned_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:720'],
            'replay_policy' => ['sometimes', Rule::in(['disabled', 'available'])],
        ];
    }
}
