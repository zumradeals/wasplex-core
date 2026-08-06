<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Http\Controllers\Advertiser;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeLibraryService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AssetsController extends Controller
{
    public function __construct(
        private readonly CreativeLibraryService $library,
        private readonly BrandService $brands,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $brandId = $request->query('brand_id');

        $brandIds = $brandId !== null
            ? [$this->brands->find($organizationId, (string) $brandId)->id]
            : $this->brands->list($organizationId)->pluck('id')->all();

        $assets = CreativeAsset::query()->whereIn('brand_id', $brandIds)->orderByDesc('created_at')->get();

        return response()->json(['assets' => $assets]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', 'string'],
            'file' => ['required', 'file'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        $asset = $this->library->upload($organizationId, $data['brand_id'], $data['file'], (string) $request->user()->id);

        return response()->json(['asset' => $asset], 201);
    }

    public function show(Request $request, string $asset): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $found = $this->library->find($organizationId, $asset);

        return response()->json(['asset' => [...$found->toArray(), 'url' => $found->url()]]);
    }

    public function destroy(Request $request, string $asset): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $this->library->delete($organizationId, $asset);

        return response()->json(status: 204);
    }
}
