<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Http\Controllers\Admin;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class BrandsController extends Controller
{
    public function __construct(private readonly BrandService $brands) {}

    public function index(): JsonResponse
    {
        return response()->json(['brands' => Brand::query()->orderByDesc('created_at')->get()]);
    }

    public function verify(string $brand): JsonResponse
    {
        $found = Brand::query()->findOrFail($brand);

        return response()->json(['brand' => $this->brands->verify($found)]);
    }

    public function reject(Request $request, string $brand): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $found = Brand::query()->findOrFail($brand);

        return response()->json(['brand' => $this->brands->reject($found, $data['reason'])]);
    }
}
