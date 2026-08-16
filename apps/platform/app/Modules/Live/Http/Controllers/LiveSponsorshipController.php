<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\AdvertiserWallet\Application\Services\InsufficientAdvertiserBalanceException;
use App\Modules\Live\Application\Services\LivePresenter;
use App\Modules\Live\Application\Services\LiveRewardSeatService;
use App\Modules\Live\Application\Services\LiveSponsorshipService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class LiveSponsorshipController
{
    public function __construct(
        private readonly LiveSponsorshipService $sponsorship,
        private readonly LiveRewardSeatService $rewardSeats,
    ) {}

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
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id, true),
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
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id, true),
        ]);
    }

    public function quote(Request $request, LiveEvent $live): JsonResponse
    {
        $data = $request->validate([
            'budget_amount_minor' => ['required', 'integer', 'min:2', 'max:2000000000'],
            'block_duration_seconds' => ['sometimes', 'integer', Rule::in([120, 300, 600])],
            'rewarded_seat_capacity' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'max_blocks_per_viewer' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $live, $data): void {
            $quote = $this->sponsorship->quote(
                $live,
                $request->user(),
                $this->advertiserOrganizationId($request),
                (int) $data['budget_amount_minor'],
                (int) ($data['block_duration_seconds'] ?? 300),
            );
            $this->rewardSeats->configureQuotePlan(
                $quote,
                isset($data['rewarded_seat_capacity']) ? (int) $data['rewarded_seat_capacity'] : null,
                isset($data['max_blocks_per_viewer']) ? (int) $data['max_blocks_per_viewer'] : null,
            );
        });

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id, true),
        ]);
    }

    public function fund(Request $request, LiveEvent $live): JsonResponse
    {
        try {
            $this->sponsorship->fund(
                $live,
                $request->user(),
                $this->advertiserOrganizationId($request),
            );
        } catch (InsufficientAdvertiserBalanceException $exception) {
            return response()->json([
                'message' => 'Ton Wallet annonceur ne contient pas encore assez de fonds pour réserver ce Live.',
                'available_minor' => $exception->availableMinor,
                'required_minor' => $exception->requestedMinor,
                'missing_minor' => max(0, $exception->requestedMinor - $exception->availableMinor),
            ], 422);
        }

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id, true),
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
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id, true),
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
            'live' => LivePresenter::live($live, (string) $request->user()->id, true),
        ]);
    }

    private function advertiserOrganizationId(Request $request): string
    {
        return (string) $request->attributes->get('advertiser_organization_id');
    }
}
