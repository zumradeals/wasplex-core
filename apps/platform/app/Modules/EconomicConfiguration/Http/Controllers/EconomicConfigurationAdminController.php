<?php

namespace App\Modules\EconomicConfiguration\Http\Controllers;

use App\Modules\EconomicConfiguration\Application\Services\EconomicConfigurationService;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Auth\AuthenticationException;
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

        return response()->json($this->service->createDraft($economicClass, $data, $this->actorId($request)), 201);
    }

    public function approve(Request $request, EconomicClassVersion $version): JsonResponse
    {
        return response()->json($this->service->approve($version, $this->actorId($request)));
    }

    public function publish(Request $request, EconomicClassVersion $version): JsonResponse
    {
        return response()->json($this->service->publish($version, $this->actorId($request)));
    }

    public function suspend(Request $request, EconomicClassVersion $version): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->suspend($version, $this->actorId($request), $data['reason']);

        return response()->json(['status' => 'suspended']);
    }

    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate(['weights' => ['required', 'array', 'size:4'], 'weights.*' => ['integer', 'between:0,10000']]);

        return response()->json($this->service->simulateWeights($data['weights']));
    }

    private function actorId(Request $request): string
    {
        $account = $request->user();

        if (! $account instanceof Account) {
            throw new AuthenticationException;
        }

        return (string) $account->getAuthIdentifier();
    }
}
