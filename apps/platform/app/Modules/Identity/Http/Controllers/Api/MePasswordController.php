<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Shared\Http\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class MePasswordController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        /** @var Account $account */
        $account = $request->user();

        if (! Hash::check($data['current_password'], $account->password)) {
            return ApiErrorResponse::make(
                $request,
                'CURRENT_PASSWORD_INVALID',
                'Le mot de passe actuel est incorrect.',
                status: 422,
            );
        }

        if (Hash::check($data['password'], $account->password)) {
            return ApiErrorResponse::make(
                $request,
                'PASSWORD_UNCHANGED',
                'Choisissez un nouveau mot de passe différent de l’ancien.',
                status: 422,
            );
        }

        $account->update(['password' => Hash::make($data['password'])]);

        $currentLaravelSessionId = $request->session()->getId();
        $revokedSessions = AccountSession::query()
            ->where('account_id', $account->id)
            ->whereNull('revoked_at')
            ->where('laravel_session_id', '!=', $currentLaravelSessionId)
            ->update(['revoked_at' => now()]);

        $this->auditLogger->record('PasswordChanged', [
            'actor_account_id' => $account->id,
            'resource_type' => 'account',
            'resource_id' => $account->id,
            'revoked_sessions' => $revokedSessions,
        ], $request);

        return new JsonResponse([
            'message' => 'Mot de passe modifié avec succès.',
            'revoked_sessions' => $revokedSessions,
        ]);
    }
}
