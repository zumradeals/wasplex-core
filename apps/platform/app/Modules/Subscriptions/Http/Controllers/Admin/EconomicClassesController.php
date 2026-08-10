<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Controllers\Admin;

use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/03 §9: "quand une campagne vise toutes les classes", les poids
 * doivent sommer à 100 %. §13: "ces valeurs [coefficients] ne doivent
 * jamais être codées en dur" — administrables et versionnées ici.
 */
final class EconomicClassesController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = EconomicClass::query()
            ->with(['versions' => function ($query): void {
                $query->whereNull('effective_to')->orderByDesc('effective_from');
            }])
            ->get();

        return response()->json(['economic_classes' => $classes]);
    }

    /**
     * Closes the current version (effective_to = now) and opens a new one
     * — the class's quota/weight/coefficient history is never overwritten
     * in place (docs/03 §13/§21: "versionné").
     */
    public function update(Request $request, string $economicClass): JsonResponse
    {
        $class = EconomicClass::query()->findOrFail($economicClass);

        $data = $request->validate([
            'quota_monthly' => ['required', 'integer', 'min:1'],
            'reward_per_complete_view_minor' => ['required', 'integer', 'min:1'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $now = now();
        $current = EconomicClassVersion::query()
            ->where('economic_class_id', $class->id)
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->firstOrFail();

        EconomicClassVersion::query()
            ->where('economic_class_id', $class->id)
            ->whereNull('effective_to')
            ->update(['effective_to' => $now]);

        $version = EconomicClassVersion::query()->create([
            'economic_class_id' => $class->id,
            'quota_monthly' => $data['quota_monthly'],
            'weight_percent' => $current->weight_percent,
            'coefficient' => $current->coefficient,
            'reward_per_complete_view_minor' => $data['reward_per_complete_view_minor'],
            'country_code' => $data['country_code'] ?? null,
            'currency' => strtoupper($data['currency']),
            'effective_from' => $now,
        ]);

        return response()->json(['economic_class_version' => $version]);
    }
}
