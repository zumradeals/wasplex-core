<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class MeController
{
    public function __construct(private AccessAuditor $auditor) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $identitySession = $request->attributes->get('identity_session');

        $account->load(['profile', 'identifiers', 'spaces.organization']);

        return response()->json([
            'data' => [
                'id' => $account->id,
                'status' => $account->status->value,
                'profile' => [
                    'display_name' => $account->profile?->display_name,
                    'avatar_path' => $account->profile?->avatar_path,
                ],
                'identifiers' => $account->identifiers->map(fn ($identifier): array => [
                    'type' => $identifier->type->value,
                    'value' => $identifier->display_value,
                    'verified' => $identifier->verified_at !== null,
                    'primary' => $identifier->is_primary,
                ])->values(),
                'active_space_id' => $identitySession?->active_space_id,
            ],
        ]);
    }

    public function spaces(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $identitySession = $request->attributes->get('identity_session');

        return response()->json([
            'data' => $account->spaces()->with('organization')->orderBy('created_at')->get()->map(
                fn ($space): array => [
                    'id' => $space->id,
                    'kind' => $space->kind->value,
                    'label' => $space->label,
                    'status' => $space->status,
                    'organization_id' => $space->organization_id,
                    'active' => $space->id === $identitySession?->active_space_id,
                ],
            ),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'locale' => ['required', 'string', 'in:fr,en'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        DB::transaction(function () use ($account, $validated): void {
            $account->forceFill([
                'locale' => $validated['locale'],
                'timezone' => $validated['timezone'],
            ])->save();

            $account->profile()->updateOrCreate([], [
                'display_name' => trim($validated['display_name']),
            ]);
        });

        $this->auditor->record(
            $request,
            'account.profile_updated',
            'success',
            'account.self.manage',
        );

        return response()->json([
            'data' => [
                'display_name' => $account->profile()->value('display_name'),
                'locale' => $account->locale,
                'timezone' => $account->timezone,
            ],
        ]);
    }
}
