<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Controllers\User;

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * docs/03 §20: chaque carte affiche nom, prix, durée, quota, classe,
 * services, accès Fonds, renouvellement, absence de revenu garanti.
 */
final class PlansController extends Controller
{
    public function index(): JsonResponse
    {
        $versions = SubscriptionPlanVersion::query()
            ->where('status', SubscriptionPlanVersion::STATUS_PUBLISHED)
            ->with(['plan', 'economicClassLink.economicClass.versions' => function ($query): void {
                $query->whereNull('effective_to')->orderByDesc('effective_from');
            }])
            ->get()
            ->map(fn (SubscriptionPlanVersion $version) => [
                'plan_version_id' => $version->id,
                'code' => $version->plan->code,
                'name' => $version->plan->name,
                'price_minor' => $version->price_minor,
                'currency' => $version->currency,
                'duration_days' => $version->duration_days,
                'services_snapshot' => $version->services_snapshot,
                'economic_class' => $version->economicClassLink?->economicClass?->code,
                'quota_monthly' => $version->economicClassLink?->economicClass?->versions->first()?->quota_monthly,
                'reward_per_attention_unit_minor' => $version->economicClassLink?->economicClass?->versions->first()?->reward_per_complete_view_minor,
                'attention_unit_seconds' => 15,
                'fonds_eligible' => $version->economicClassLink?->economicClass?->code !== 'FREE',
            ]);

        return response()->json(['plans' => $versions]);
    }
}
