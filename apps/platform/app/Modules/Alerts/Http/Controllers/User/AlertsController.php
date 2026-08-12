<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;
use App\Modules\Alerts\Infrastructure\Models\AlertInstitutionalCase;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AlertsController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $alerts = AlertDeclaration::query()
            ->with('institutionalCases.organization')
            ->where('account_id', $account->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AlertDeclaration $alert): array => $this->memberProjection($alert));

        return new JsonResponse(['alerts' => $alerts]);
    }

    public function publicFeed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(['object', 'document', 'vehicle', 'person', 'sos'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $query = AlertDeclaration::query()
            ->where('status', 'published')
            ->where('public_visibility', true)
            ->whereNotNull('published_at')
            ->whereNull('resolved_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if (isset($data['category'])) {
            $query->where('category', $data['category']);
        }

        $alerts = $query
            ->orderByRaw("case priority when 'P0' then 0 when 'P1' then 1 when 'P2' then 2 when 'P3' then 3 else 4 end")
            ->orderByDesc('published_at')
            ->limit((int) ($data['limit'] ?? 12))
            ->get()
            ->map(fn (AlertDeclaration $alert): array => $this->publicProjection($alert));

        return new JsonResponse(['alerts' => $alerts]);
    }

    public function feedConfiguration(): JsonResponse
    {
        $settings = DB::table('alert_feed_settings')->first();

        return new JsonResponse([
            'configuration' => [
                'useful_content_interval' => (int) ($settings?->useful_content_interval ?? 5),
                'circles_enabled' => (bool) ($settings?->circles_enabled ?? true),
                'left_rail_enabled' => (bool) ($settings?->left_rail_enabled ?? true),
                'full_screen_enabled' => (bool) ($settings?->full_screen_enabled ?? true),
                'tips' => $settings?->tips ? json_decode((string) $settings->tips, true, flags: JSON_THROW_ON_ERROR) : [],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(['object', 'document', 'vehicle', 'person', 'sos'])],
            'situation' => ['required', Rule::in(['lost', 'found', 'stolen', 'missing', 'emergency', 'other'])],
            'title' => ['required', 'string', 'min:4', 'max:140'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'city' => ['nullable', 'string', 'max:80'],
            'area_label' => ['nullable', 'string', 'max:120'],
            'exact_location' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        $priority = $data['category'] === 'sos' ? 'P0' : ($data['category'] === 'person' ? 'P2' : 'P3');

        $alert = AlertDeclaration::query()->create([
            'account_id' => $account->id,
            ...$data,
            'priority' => $priority,
            'status' => 'submitted',
            'public_visibility' => false,
            'submitted_at' => now(),
        ]);

        return new JsonResponse(['alert' => $this->memberProjection($alert)], 201);
    }

    /** @return array<string, mixed> */
    private function memberProjection(AlertDeclaration $alert): array
    {
        /** @var AlertInstitutionalCase|null $case */
        $case = $alert->relationLoaded('institutionalCases')
            ? $alert->institutionalCases->sortByDesc('referred_at')->first()
            : null;

        return [
            'id' => $alert->id,
            'category' => $alert->category,
            'situation' => $alert->situation,
            'priority' => $alert->priority,
            'status' => $alert->status,
            'title' => $alert->title,
            'description' => $alert->description,
            'public_summary' => $alert->public_summary,
            'rejection_reason' => $alert->rejection_reason,
            'city' => $alert->city,
            'area_label' => $alert->area_label,
            'public_visibility' => $alert->public_visibility,
            'submitted_at' => $alert->submitted_at?->toIso8601String(),
            'reviewed_at' => $alert->reviewed_at?->toIso8601String(),
            'published_at' => $alert->published_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'institutional_status' => $this->institutionalStatus($case),
            'institutional_case' => $case === null ? null : [
                'id' => $case->id,
                'status' => $case->status,
                'institution_name' => $case->organization?->name,
                'institutional_reference' => $case->institutional_reference,
                'referred_at' => $case->referred_at?->toIso8601String(),
                'acknowledged_at' => $case->acknowledged_at?->toIso8601String(),
                'started_at' => $case->started_at?->toIso8601String(),
                'resolved_at' => $case->resolved_at?->toIso8601String(),
            ],
        ];
    }

    private function institutionalStatus(?AlertInstitutionalCase $case): ?string
    {
        return match ($case?->status) {
            AlertInstitutionalCase::STATUS_REFERRED => 'forwarded',
            AlertInstitutionalCase::STATUS_ACKNOWLEDGED,
            AlertInstitutionalCase::STATUS_IN_PROGRESS => 'taken_charge',
            AlertInstitutionalCase::STATUS_RESOLVED => 'resolved',
            AlertInstitutionalCase::STATUS_DECLINED => 'declined',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function publicProjection(AlertDeclaration $alert): array
    {
        return [
            'id' => $alert->id,
            'category' => $alert->category,
            'situation' => $alert->situation,
            'priority' => $alert->priority,
            'title' => $alert->title,
            'summary' => $alert->public_summary ?: $alert->description,
            'city' => $alert->city,
            'area_label' => $alert->area_label,
            'published_at' => $alert->published_at?->toIso8601String(),
        ];
    }
}
