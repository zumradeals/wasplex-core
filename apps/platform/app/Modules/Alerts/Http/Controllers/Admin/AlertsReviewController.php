<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;
use App\Modules\Identity\Application\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AlertsReviewController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        $alerts = AlertDeclaration::query()
            ->whereIn('status', ['submitted', 'published', 'rejected'])
            ->latest('submitted_at')
            ->limit(100)
            ->get()
            ->map(fn (AlertDeclaration $alert): array => [
                'id' => $alert->id,
                'account_id' => $alert->account_id,
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
                'submitted_at' => $alert->submitted_at?->toIso8601String(),
                'reviewed_at' => $alert->reviewed_at?->toIso8601String(),
                'published_at' => $alert->published_at?->toIso8601String(),
            ]);

        return new JsonResponse(['alerts' => $alerts]);
    }

    public function publish(Request $request, AlertDeclaration $alert): JsonResponse
    {
        $data = $request->validate([
            'public_summary' => ['nullable', 'string', 'max:1200'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        if ($alert->status !== 'submitted') {
            return new JsonResponse(['message' => 'Cette alerte ne peut pas être publiée.'], 422);
        }

        $alert->update([
            'status' => 'published',
            'public_visibility' => true,
            'public_summary' => trim((string) ($data['public_summary'] ?? $alert->description)),
            'rejection_reason' => null,
            'reviewed_by_account_id' => $request->user()?->id,
            'reviewed_at' => now(),
            'published_at' => now(),
            'expires_at' => now()->addHours((int) ($data['expires_in_hours'] ?? 168)),
        ]);

        $this->auditLogger->record('AlertDeclarationPublished', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'alert_declaration',
            'resource_id' => $alert->id,
            'subject_account_id' => $alert->account_id,
            'priority' => $alert->priority,
            'expires_at' => $alert->expires_at?->toIso8601String(),
        ], $request);

        return new JsonResponse(['alert' => ['id' => $alert->id, 'status' => 'published']]);
    }

    public function reject(Request $request, AlertDeclaration $alert): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($alert->status !== 'submitted') {
            return new JsonResponse(['message' => 'Cette alerte ne peut pas être refusée.'], 422);
        }

        $alert->update([
            'status' => 'rejected',
            'public_visibility' => false,
            'rejection_reason' => trim($data['reason']),
            'reviewed_by_account_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $this->auditLogger->record('AlertDeclarationRejected', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'alert_declaration',
            'resource_id' => $alert->id,
            'subject_account_id' => $alert->account_id,
            'priority' => $alert->priority,
        ], $request);

        return new JsonResponse(['alert' => ['id' => $alert->id, 'status' => 'rejected']]);
    }

    public function updateFeedSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'useful_content_interval' => ['required', 'integer', 'min:1', 'max:30'],
            'circles_enabled' => ['required', 'boolean'],
            'left_rail_enabled' => ['required', 'boolean'],
            'full_screen_enabled' => ['required', 'boolean'],
            'tips' => ['nullable', 'array', 'max:20'],
            'tips.*.title' => ['required_with:tips', 'string', 'max:120'],
            'tips.*.body' => ['required_with:tips', 'string', 'max:500'],
        ]);

        $settings = DB::table('alert_feed_settings')->first();
        if ($settings === null) {
            return new JsonResponse(['message' => 'Configuration Alertes introuvable.'], 500);
        }

        DB::table('alert_feed_settings')->where('id', $settings->id)->update([
            'useful_content_interval' => $data['useful_content_interval'],
            'circles_enabled' => $data['circles_enabled'],
            'left_rail_enabled' => $data['left_rail_enabled'],
            'full_screen_enabled' => $data['full_screen_enabled'],
            'tips' => json_encode($data['tips'] ?? [], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $this->auditLogger->record('AlertFeedSettingsUpdated', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'alert_feed_settings',
            'resource_id' => $settings->id,
            'useful_content_interval' => $data['useful_content_interval'],
            'circles_enabled' => $data['circles_enabled'],
            'left_rail_enabled' => $data['left_rail_enabled'],
            'full_screen_enabled' => $data['full_screen_enabled'],
            'tips_count' => count($data['tips'] ?? []),
        ], $request);

        return new JsonResponse(['updated' => true]);
    }
}
