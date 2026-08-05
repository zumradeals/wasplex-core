<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use Illuminate\Http\JsonResponse;

final class OrganizationMembersController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $members = $organization->memberships()
            ->with('account.profile')
            ->get()
            ->map(fn (OrganizationMembership $m) => [
                'account_id' => $m->account_id,
                'display_name' => $m->account->profile?->display_name,
                'title' => $m->title,
                'status' => $m->status,
                'joined_at' => $m->joined_at->toIso8601String(),
            ]);

        return new JsonResponse(['members' => $members->values()]);
    }

    public function destroy(Organization $organization, OrganizationMembership $membership): JsonResponse
    {
        if ($membership->organization_id !== $organization->id) {
            return new JsonResponse(['code' => 'MEMBERSHIP_NOT_FOUND'], 404);
        }

        $membership->update(['status' => 'removed']);

        return new JsonResponse(status: 204);
    }
}
