<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Http\Controllers\Advertiser;

use App\Modules\AdvertiserStudio\Application\Services\AdvertiserProfileService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\AdvertiserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class ProfileController extends Controller
{
    public function __construct(private readonly AdvertiserProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $profile = $this->profiles->getOrCreate($organizationId);

        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'advertiser_type' => ['sometimes', Rule::in([
                AdvertiserProfile::TYPE_INDIVIDUAL,
                AdvertiserProfile::TYPE_BUSINESS,
                AdvertiserProfile::TYPE_AGENCY,
                AdvertiserProfile::TYPE_INSTITUTIONAL,
            ])],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $profile = $this->profiles->update($organizationId, $data);

        return response()->json(['profile' => $profile]);
    }
}
