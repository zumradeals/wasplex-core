<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AdminController
{
    public function __construct(
        private CapabilityService $capabilities,
        private AccessAuditor $auditor,
    ) {}

    public function accounts(): JsonResponse
    {
        return response()->json([
            'data' => Account::query()
                ->with(['profile', 'identifiers'])
                ->latest()
                ->paginate(25)
                ->through(fn (Account $account): array => [
                    'id' => $account->id,
                    'status' => $account->status->value,
                    'display_name' => $account->profile?->display_name,
                    'identifier' => $account->identifiers->firstWhere('is_primary', true)?->display_value,
                    'created_at' => $account->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function organizations(): JsonResponse
    {
        return response()->json([
            'data' => Organization::query()
                ->withCount('memberships')
                ->latest()
                ->paginate(25)
                ->through(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'kind' => $organization->kind,
                    'status' => $organization->status,
                    'members_count' => $organization->memberships_count,
                    'created_at' => $organization->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function grant(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'capability' => ['required', 'string', 'max:160', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'space_id' => ['nullable', 'ulid', 'exists:user_spaces,id'],
            'organization_id' => ['nullable', 'ulid', 'exists:organizations,id'],
            'scope' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $spaceId = $validated['space_id'] ?? null;
        $space = is_string($spaceId) ? UserSpace::query()->findOrFail($spaceId) : null;
        abort_if($space !== null && $space->account_id !== $account->id, 422, 'L’espace ne correspond pas au compte.');

        /** @var Account $actor */
        $actor = $request->user();
        $grant = $this->capabilities->grant(
            account: $account,
            capability: $validated['capability'],
            grantedBy: $actor,
            space: $space,
            organizationId: is_string($validated['organization_id'] ?? null) ? $validated['organization_id'] : null,
            scope: is_array($validated['scope'] ?? null) ? $validated['scope'] : null,
            expiresAt: is_string($validated['expires_at'] ?? null) ? new \DateTimeImmutable($validated['expires_at']) : null,
            reason: is_string($validated['reason']) ? $validated['reason'] : '',
        );

        $this->auditor->record(
            $request,
            'capability.granted',
            'success',
            'admin.capabilities.grant',
            ['grant_id' => $grant->id, 'target_account_id' => $account->id, 'capability' => $grant->capability],
        );

        return response()->json(['data' => ['grant_id' => $grant->id]], 201);
    }

    public function revoke(Request $request, CapabilityGrant $grant): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        abort_if($grant->revoked_at !== null, 409, 'Cette capacité est déjà révoquée.');

        $this->capabilities->revoke($grant);
        $this->auditor->record(
            $request,
            'capability.revoked',
            'success',
            'admin.capabilities.revoke',
            ['grant_id' => $grant->id, 'reason' => $validated['reason']],
        );

        return response()->json(['data' => ['revoked' => true]]);
    }
}
