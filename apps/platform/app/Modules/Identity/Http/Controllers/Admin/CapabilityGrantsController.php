<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CapabilityGrantsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        $grants = CapabilityGrant::query()
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (CapabilityGrant $g) => [
                'id' => $g->id,
                'account_id' => $g->account_id,
                'capability_code' => $g->capability_code,
                'scope_type' => $g->scope_type,
                'scope_id' => $g->scope_id,
                'status' => $g->status,
                'starts_at' => $g->starts_at->toIso8601String(),
                'expires_at' => $g->expires_at?->toIso8601String(),
            ]);

        return new JsonResponse(['grants' => $grants->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'string', 'exists:accounts,id'],
            'capability_code' => ['required', 'string', 'max:120'],
            'scope_type' => ['nullable', Rule::in([CapabilityGrant::SCOPE_ORGANIZATION, CapabilityGrant::SCOPE_SPACE])],
            'scope_id' => ['nullable', 'string', 'required_with:scope_type'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        /** @var Account $grantor */
        $grantor = $request->user();

        $grant = CapabilityGrant::create([
            ...$data,
            'status' => 'active',
            'starts_at' => now(),
            'granted_by' => $grantor->id,
        ]);

        $this->auditLogger->record('CapabilityGranted', [
            'actor_account_id' => $grantor->id,
            'capability_code' => $grant->capability_code,
            'resource_type' => 'account',
            'resource_id' => $grant->account_id,
        ], $request);

        return new JsonResponse(['id' => $grant->id], 201);
    }

    public function destroy(Request $request, CapabilityGrant $grant): JsonResponse
    {
        /** @var Account $revoker */
        $revoker = $request->user();

        $grant->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $revoker->id]);

        $this->auditLogger->record('CapabilityRevoked', [
            'actor_account_id' => $revoker->id,
            'capability_code' => $grant->capability_code,
            'resource_type' => 'account',
            'resource_id' => $grant->account_id,
        ], $request);

        return new JsonResponse(status: 204);
    }
}
