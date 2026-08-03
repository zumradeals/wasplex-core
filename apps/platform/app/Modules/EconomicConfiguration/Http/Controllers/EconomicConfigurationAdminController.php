<?php

namespace App\Modules\EconomicConfiguration\Http\Controllers;

use App\Modules\EconomicConfiguration\Application\Services\EconomicConfigurationService;
use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EconomicConfigurationAdminController
{
    public function __construct(private EconomicConfigurationService $service) {}

    public function index(Request $request): Response|JsonResponse
    {
        $classes = EconomicClass::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
            ->orderBy('code')
            ->get()
            ->map(fn (EconomicClass $economicClass): array => $this->classPayload($economicClass))
            ->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $classes]);
        }

        return Inertia::render('EconomicConfiguration/Admin', [
            'classes' => $classes,
            'flash' => [
                'success' => $request->session()->get('success'),
                'simulation' => $request->session()->get('economic_simulation'),
            ],
        ]);
    }

    public function store(Request $request, EconomicClass $economicClass): JsonResponse|RedirectResponse
    {
        /** @var array{public_name:string,quota_monthly:int,weight_basis_points:int,targeting_coefficient_basis_points:int,features?:array<string,mixed>} $data */
        $data = $request->validate([
            'public_name' => ['required', 'string', 'max:120'],
            'quota_monthly' => ['required', 'integer', 'min:0'],
            'weight_basis_points' => ['required', 'integer', 'between:0,10000'],
            'targeting_coefficient_basis_points' => ['required', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
            'features.fund_eligible' => ['sometimes', 'boolean'],
        ]);

        $version = $this->service->createDraft($economicClass, $data, $this->actorId($request));

        return $this->respond(
            $request,
            $this->versionPayload($version),
            "La version {$version->version} de la classe {$economicClass->code} a été créée en brouillon.",
            201,
        );
    }

    public function approve(Request $request, EconomicClassVersion $version): JsonResponse|RedirectResponse
    {
        $version = $this->service->approve($version, $this->actorId($request));

        return $this->respond(
            $request,
            $this->versionPayload($version),
            "La version {$version->version} a été approuvée.",
        );
    }

    public function publish(Request $request, EconomicClassVersion $version): JsonResponse|RedirectResponse
    {
        $version = $this->service->publish($version, $this->actorId($request));

        return $this->respond(
            $request,
            $this->versionPayload($version),
            "La version {$version->version} est maintenant publiée.",
        );
    }

    public function publishMany(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{version_ids:list<string>} $data */
        $data = $request->validate([
            'version_ids' => ['required', 'array', 'min:1', 'max:4'],
            'version_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        $versions = $this->service->publishMany($data['version_ids'], $this->actorId($request));
        $payload = $versions
            ->map(fn (EconomicClassVersion $version): array => $this->versionPayload($version))
            ->values();

        return $this->respond(
            $request,
            $payload,
            $versions->count() === 1
                ? 'La version sélectionnée est maintenant publiée.'
                : "Les {$versions->count()} versions sélectionnées ont été publiées atomiquement.",
        );
    }

    public function suspend(Request $request, EconomicClassVersion $version): JsonResponse|RedirectResponse
    {
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->suspend($version, $this->actorId($request), $data['reason']);

        return $this->respond(
            $request,
            ['status' => ConfigurationState::Suspended->value],
            "La version {$version->version} a été suspendue.",
        );
    }

    public function simulate(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{weights:array<int,int|string>} $data */
        $data = $request->validate([
            'weights' => ['required', 'array', 'size:4'],
            'weights.*' => ['integer', 'between:0,10000'],
        ]);
        $simulation = $this->service->simulateWeights($data['weights']);

        if ($request->expectsJson()) {
            return response()->json($simulation);
        }

        return redirect()
            ->route('admin.economy.index')
            ->with('economic_simulation', $simulation)
            ->with('success', $simulation['valid']
                ? 'La répartition simulée totalise exactement 100 %.'
                : 'La répartition simulée doit être corrigée avant publication.');
    }

    /** @return array<string, mixed> */
    private function classPayload(EconomicClass $economicClass): array
    {
        $versions = $economicClass->versions;
        $latest = $versions->first();
        $published = $versions->first(
            fn (EconomicClassVersion $version): bool => $version->state === ConfigurationState::Published
                && $version->effective_to === null,
        );

        return [
            'id' => $economicClass->id,
            'code' => $economicClass->code,
            'status' => $economicClass->status,
            'latest' => $latest instanceof EconomicClassVersion ? $this->versionPayload($latest) : null,
            'published' => $published instanceof EconomicClassVersion ? $this->versionPayload($published) : null,
            'versions' => $versions
                ->take(12)
                ->map(fn (EconomicClassVersion $version): array => $this->versionPayload($version))
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionPayload(EconomicClassVersion $version): array
    {
        $features = $version->features ?? [];

        return [
            'id' => $version->id,
            'version' => $version->version,
            'state' => $version->state->value,
            'publicName' => $version->public_name,
            'quotaMonthly' => $version->quota_monthly,
            'weightBasisPoints' => $version->weight_basis_points,
            'targetingCoefficientBasisPoints' => $version->targeting_coefficient_basis_points,
            'fundEligible' => (bool) ($features['fund_eligible'] ?? false),
            'effectiveFrom' => $this->date($version->effective_from),
            'effectiveTo' => $this->date($version->effective_to),
            'approvedAt' => $this->date($version->approved_at),
            'publishedAt' => $this->date($version->published_at),
            'createdAt' => $this->date($version->created_at),
            'suspensionReason' => $version->suspension_reason,
        ];
    }

    private function respond(
        Request $request,
        mixed $payload,
        string $message,
        int $status = 200,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json($payload, $status);
        }

        return redirect()->route('admin.economy.index')->with('success', $message);
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
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
