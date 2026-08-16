<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Live\Application\Services\LivePresenter;
use App\Modules\Live\Application\Services\LiveSponsorshipService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LiveSponsorshipController
{
    public function __construct(private readonly LiveSponsorshipService $sponsorship) {}

    public function configure(Request $request, LiveEvent $live): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'economic_classes' => ['sometimes', 'array', 'min:1', 'max:20'],
            'economic_classes.*' => ['required', 'string', 'max:40'],
        ]);

        $this->sponsorship->configure(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
            $data,
        );

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id),
        ]);
    }

    public function estimate(Request $request, LiveEvent $live): JsonResponse
    {
        $estimate = $this->sponsorship->estimate(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json([
            'estimate' => [
                'estimated_reach_min' => $estimate->estimatedMin,
                'estimated_reach_max' => $estimate->estimatedMax,
                'too_small' => $estimate->tooSmall,
            ],
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id),
        ]);
    }

    public function quote(Request $request, LiveEvent $live): JsonResponse
    {
        $data = $request->validate([
            'budget_amount_minor' => ['required', 'integer', 'min:2', 'max:2000000000'],
            'block_duration_seconds' => ['sometimes', 'integer', Rule::in([120, 300, 600])],
        ]);

        $this->sponsorship->quote(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
            (int) $data['budget_amount_minor'],
            (int) ($data['block_duration_seconds'] ?? 300),
        );

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id),
        ]);
    }

    public function fund(Request $request, LiveEvent $live): JsonResponse
    {
        $this->sponsorship->fund(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id),
        ]);
    }

    public function budget(Request $request, LiveEvent $live): JsonResponse
    {
        $this->sponsorship->assertCanManage(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id),
        ]);
    }

    public function cancel(Request $request, LiveEvent $live): JsonResponse
    {
        $live = $this->sponsorship->cancel(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json([
            'live' => LivePresenter::live($live, (string) $request->user()->id),
        ]);
    }

    private function advertiserOrganizationId(Request $request): string
    {
        return (string) $request->attributes->get('advertiser_organization_id');
    }
}
