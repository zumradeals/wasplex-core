<?php

namespace App\Modules\EconomicConfiguration\Http\Controllers;

use App\Modules\EconomicConfiguration\Application\Services\EconomicConfigurationService;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class EconomicConfigurationAdminController
{
    public function __construct(private EconomicConfigurationService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(EconomicClass::query()->with(['versions' => fn ($query) => $query->latest('version')])->orderBy('code')->get());
    }

    public function store(Request $request, EconomicClass $economicClass): JsonResponse
    {
        $data = $request->validate([
            'public_name' => ['required', 'string', 'max:120'],
            'quota_monthly' => ['required', 'integer', 'min:0'],
            'weight_basis_points' => ['required', 'integer', 'between:0,10000'],
            'targeting_coefficient_basis_points' => ['required', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
        ]);

        return response()->json($this->service->createDraft($economicClass, $data, (string) $request->user()->getAuthIdentifier()), 201);
    }

    public function approve(Request $request, EconomicClassVersion $version): JsonResponse
    {
        return response()->json($this->service->approve($version, (string) $request->user()->getAuthIdentifier()));
    }

    public function publish(Request $request, EconomicClassVersion $version): JsonResponse
    {
        return response()->json($this->service->publish($version, (string) $request->user()->getAuthIdentifier()));
    }

    public function suspend(Request $request, EconomicClassVersion $version): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->suspend($version, (string) $request->user()->getAuthIdentifier(), $data['reason']);

        return response()->json(['status' => 'suspended']);
    }

    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate(['weights' => ['required', 'array', 'size:4'], 'weights.*' => ['integer', 'between:0,10000']]);

        return response()->json($this->service->simulateWeights($data['weights']));
    }
}
