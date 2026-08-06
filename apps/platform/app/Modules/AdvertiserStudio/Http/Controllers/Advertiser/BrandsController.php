<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Http\Controllers\Advertiser;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class BrandsController extends Controller
{
    public function __construct(private readonly BrandService $brands) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        return response()->json(['brands' => $this->brands->list($organizationId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'sector' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slogan' => ['nullable', 'string', 'max:180'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $brand = $this->brands->create($organizationId, $data);

        return response()->json(['brand' => $brand], 201);
    }

    public function show(Request $request, string $brand): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        return response()->json(['brand' => $this->brands->find($organizationId, $brand)]);
    }

    public function update(Request $request, string $brand): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'slogan' => ['sometimes', 'nullable', 'string', 'max:180'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_logo_asset_id' => ['sometimes', 'nullable', 'string'],
            'secondary_logo_asset_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $updated = $this->brands->update($organizationId, $brand, $data);

        return response()->json(['brand' => $updated]);
    }

    public function updateColors(Request $request, string $brand): JsonResponse
    {
        $data = $request->validate([
            'colors' => ['present', 'array'],
            'colors.*.name' => ['required', 'string', 'max:60'],
            'colors.*.hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'colors.*.rgb' => ['nullable', 'string', 'max:30'],
            'colors.*.cmyk' => ['nullable', 'string', 'max:30'],
            'colors.*.usage' => ['required', 'string', 'max:30'],
            'colors.*.priority' => ['nullable', 'integer', 'min:0'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $updated = $this->brands->replaceColors($organizationId, $brand, $data['colors']);

        return response()->json(['brand' => $updated]);
    }

    public function updateTypographies(Request $request, string $brand): JsonResponse
    {
        $data = $request->validate([
            'typographies' => ['present', 'array'],
            'typographies.*.role' => ['required', 'string', 'max:30'],
            'typographies.*.family' => ['required', 'string', 'max:80'],
            'typographies.*.usages' => ['nullable', 'string', 'max:500'],
            'typographies.*.recommended_sizes' => ['nullable', 'string', 'max:120'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $updated = $this->brands->replaceTypographies($organizationId, $brand, $data['typographies']);

        return response()->json(['brand' => $updated]);
    }

    public function updateGuidelines(Request $request, string $brand): JsonResponse
    {
        $data = $request->validate([
            'tone' => ['nullable', 'string', 'max:60'],
            'custom_instructions' => ['nullable', 'string', 'max:2000'],
            'mandatory_mentions' => ['nullable', 'string', 'max:1000'],
            'forbidden_mentions' => ['nullable', 'string', 'max:1000'],
            'usage_rules' => ['nullable', 'string', 'max:2000'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $updated = $this->brands->updateGuidelines($organizationId, $brand, $data);

        return response()->json(['brand' => $updated]);
    }
}
