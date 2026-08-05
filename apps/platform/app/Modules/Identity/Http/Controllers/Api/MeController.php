<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $account->load('profile', 'identifiers');

        return new JsonResponse([
            'id' => $account->id,
            'status' => $account->status,
            'country_code' => $account->country_code,
            'language' => $account->language,
            'mfa_enabled' => $account->totp_enabled_at !== null,
            'profile' => [
                'first_name' => $account->profile?->first_name,
                'last_name' => $account->profile?->last_name,
                'display_name' => $account->profile?->display_name,
            ],
            'identifiers' => $account->identifiers->map(fn ($i) => [
                'type' => $i->type,
                'value' => $i->value,
                'is_primary' => $i->is_primary,
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $account->profile()->updateOrCreate([], $data);

        return $this->show($request);
    }
}
