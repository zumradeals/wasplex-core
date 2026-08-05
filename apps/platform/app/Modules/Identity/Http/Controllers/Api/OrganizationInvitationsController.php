<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\OrganizationInvitationService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OrganizationInvitationsController extends Controller
{
    public function __construct(private readonly OrganizationInvitationService $invitations) {}

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'identifier_type' => ['required', Rule::in(['email', 'phone'])],
            'identifier_value' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var Account $inviter */
        $inviter = $request->user();

        [$invitation, $token] = $this->invitations->invite(
            $organization,
            $inviter,
            $data['identifier_type'],
            $data['identifier_value'],
            $data['title'] ?? null,
        );

        // Pas d'adaptateur d'envoi en P001 (docs/chantiers/P001-CHANTIER.md,
        // exclusion explicite) : le jeton est renvoyé pour transmission
        // hors bande par l'organisation invitante.
        return new JsonResponse([
            'id' => $invitation->id,
            'token' => $token,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 201);
    }

    public function accept(Request $request, OrganizationInvitation $invitation): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        /** @var Account $account */
        $account = $request->user();

        $membership = $this->invitations->accept($invitation, $account, $data['token']);

        return new JsonResponse(['organization_id' => $membership->organization_id]);
    }
}
