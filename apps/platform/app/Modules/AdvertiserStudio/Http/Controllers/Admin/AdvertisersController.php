<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Http\Controllers\Admin;

use App\Modules\AdvertiserStudio\Application\Services\AdvertiserProfileService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\AdvertiserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class AdvertisersController extends Controller
{
    public function __construct(private readonly AdvertiserProfileService $profiles) {}

    public function index(): JsonResponse
    {
        return response()->json(['advertisers' => AdvertiserProfile::query()->orderByDesc('created_at')->get()]);
    }

    public function show(string $advertiser): JsonResponse
    {
        $profile = AdvertiserProfile::query()->with('brands')->findOrFail($advertiser);

        return response()->json(['advertiser' => $profile]);
    }

    public function verify(string $advertiser): JsonResponse
    {
        $profile = AdvertiserProfile::query()->findOrFail($advertiser);

        return response()->json(['advertiser' => $this->profiles->verify($profile)]);
    }

    public function restrict(string $advertiser): JsonResponse
    {
        $profile = AdvertiserProfile::query()->findOrFail($advertiser);

        return response()->json(['advertiser' => $this->profiles->restrict($profile)]);
    }

    public function restore(string $advertiser): JsonResponse
    {
        $profile = AdvertiserProfile::query()->findOrFail($advertiser);

        return response()->json(['advertiser' => $this->profiles->restore($profile)]);
    }
}
