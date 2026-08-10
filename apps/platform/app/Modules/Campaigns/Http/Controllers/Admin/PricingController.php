<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Admin;

use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceCatalog;
use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * docs/05 §30. Only the price catalog is administered here — campaign
 * review itself belongs to P007 (out of scope, docs/chantiers/
 * P006-CHANTIER.md §4).
 */
final class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $versions = AdvertisingPriceVersion::query()
            ->with('catalog')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['price_versions' => $versions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'catalog_code' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'minimum_budget_minor' => ['required', 'integer', 'min:1'],
            'minimum_daily_budget_minor' => ['required', 'integer', 'min:1'],
            'minimum_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'maximum_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $this->validateDurations($data);

        $catalog = AdvertisingPriceCatalog::query()->firstOrCreate(['code' => $data['catalog_code']]);

        $version = AdvertisingPriceVersion::query()->create([
            'catalog_id' => $catalog->id,
            'status' => AdvertisingPriceVersion::STATUS_DRAFT,
            'currency' => strtoupper($data['currency']),
            'base_price_minor_per_event' => 0,
            'image_multiplier' => 1,
            'video_multiplier' => 1,
            'minimum_budget_minor' => $data['minimum_budget_minor'],
            'minimum_daily_budget_minor' => $data['minimum_daily_budget_minor'],
            'minimum_duration_days' => $data['minimum_duration_days'],
            'maximum_duration_days' => $data['maximum_duration_days'],
            'duration_days' => $data['duration_days'],
        ]);

        return response()->json(['price_version' => $version->refresh()->load('catalog')], 201);
    }

    public function update(Request $request, string $priceVersion): JsonResponse
    {
        $version = AdvertisingPriceVersion::query()->findOrFail($priceVersion);

        if ($version->status !== AdvertisingPriceVersion::STATUS_DRAFT) {
            return response()->json(['message' => 'Seule une version en brouillon peut être modifiée.'], 409);
        }

        $data = $request->validate([
            'minimum_budget_minor' => ['sometimes', 'integer', 'min:1'],
            'minimum_daily_budget_minor' => ['sometimes', 'integer', 'min:1'],
            'minimum_duration_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'maximum_duration_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'duration_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $this->validateDurations(array_merge($version->only([
            'minimum_duration_days', 'maximum_duration_days', 'duration_days',
        ]), $data));

        $version->update($data);

        return response()->json(['price_version' => $version->refresh()]);
    }

    public function publish(string $priceVersion): JsonResponse
    {
        $version = AdvertisingPriceVersion::query()->findOrFail($priceVersion);

        AdvertisingPriceVersion::query()
            ->where('catalog_id', $version->catalog_id)
            ->where('status', AdvertisingPriceVersion::STATUS_PUBLISHED)
            ->update(['status' => AdvertisingPriceVersion::STATUS_SUSPENDED, 'effective_to' => now()]);

        $version->update([
            'status' => AdvertisingPriceVersion::STATUS_PUBLISHED,
            'effective_from' => now(),
            'published_at' => now(),
        ]);

        return response()->json(['price_version' => $version->refresh()]);
    }

    /** @param array<string, mixed> $data */
    private function validateDurations(array $data): void
    {
        $minimum = (int) $data['minimum_duration_days'];
        $maximum = (int) $data['maximum_duration_days'];
        $default = (int) $data['duration_days'];

        if ($minimum > $maximum || $default < $minimum || $default > $maximum) {
            throw ValidationException::withMessages([
                'duration_days' => 'La durée proposée doit être comprise entre la durée minimale et maximale.',
            ]);
        }
    }
}
