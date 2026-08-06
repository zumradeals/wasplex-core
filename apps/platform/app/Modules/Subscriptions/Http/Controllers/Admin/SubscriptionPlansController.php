<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Controllers\Admin;

use App\Modules\Subscriptions\Infrastructure\Models\PlanEconomicClassLink;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * docs/03 §21: "création et versionnement des plans, rattachement aux
 * classes, configuration des quotas/poids/coefficients, dates d'effet,
 * suspension". {id} in the versioned routes below refers to a
 * subscription_plan_versions id — that is where all mutable, versioned
 * state actually lives (docs/chantiers/P004-CHANTIER.md interpretation,
 * since the corpus API list doesn't disambiguate plan vs plan-version ids).
 */
final class SubscriptionPlansController extends Controller
{
    public function index(): JsonResponse
    {
        $versions = SubscriptionPlanVersion::query()
            ->with(['plan', 'economicClassLink.economicClass'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['plan_versions' => $versions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:64'],
            'plan_name' => ['required', 'string', 'max:120'],
            'economic_class_id' => ['required', 'string', Rule::exists('economic_classes', 'id')],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'services_snapshot' => ['nullable', 'array'],
        ]);

        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['code' => $data['plan_code']],
            ['name' => $data['plan_name']],
        );

        $version = SubscriptionPlanVersion::query()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionPlanVersion::STATUS_DRAFT,
            'price_minor' => $data['price_minor'],
            'currency' => strtoupper($data['currency']),
            'duration_days' => $data['duration_days'],
            'services_snapshot' => $data['services_snapshot'] ?? null,
        ]);

        PlanEconomicClassLink::query()->create([
            'plan_version_id' => $version->id,
            'economic_class_id' => $data['economic_class_id'],
        ]);

        return response()->json(['plan_version' => $version->refresh()->load('economicClassLink.economicClass')], 201);
    }

    public function update(Request $request, string $planVersion): JsonResponse
    {
        $version = SubscriptionPlanVersion::query()->findOrFail($planVersion);

        if ($version->status !== SubscriptionPlanVersion::STATUS_DRAFT) {
            return response()->json(['message' => 'Seul un plan en brouillon peut être modifié.'], 409);
        }

        $data = $request->validate([
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'services_snapshot' => ['sometimes', 'nullable', 'array'],
        ]);

        $version->update($data);

        return response()->json(['plan_version' => $version->refresh()]);
    }

    public function publish(string $planVersion): JsonResponse
    {
        $version = SubscriptionPlanVersion::query()->findOrFail($planVersion);
        $version->update(['status' => SubscriptionPlanVersion::STATUS_PUBLISHED, 'effective_from' => now()]);

        return response()->json(['plan_version' => $version->refresh()]);
    }

    public function suspend(string $planVersion): JsonResponse
    {
        $version = SubscriptionPlanVersion::query()->findOrFail($planVersion);
        $version->update(['status' => SubscriptionPlanVersion::STATUS_SUSPENDED, 'effective_to' => now()]);

        return response()->json(['plan_version' => $version->refresh()]);
    }
}
