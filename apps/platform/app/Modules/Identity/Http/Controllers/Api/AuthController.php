<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AccountRegistrationService;
use App\Modules\Identity\Application\Services\AccountSessionManager;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Shared\Http\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AccountRegistrationService $registration,
        private readonly AccountSessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier_type' => ['required', Rule::in(['email', 'phone'])],
            'identifier_value' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'country_code' => ['required', 'string', 'size:2'],
            'language' => ['nullable', 'string', 'max:5'],
        ]);

        $account = $this->registration->register(
            $data['identifier_type'],
            $data['identifier_value'],
            $data['password'],
            strtoupper($data['country_code']),
            $data['language'] ?? 'fr',
        );

        $this->auditLogger->record('AccountCreated', ['actor_account_id' => $account->id], $request);

        return new JsonResponse(['id' => $account->id], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier_value' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = AccountIdentifier::query()->where('value', $data['identifier_value'])->first();

        if ($identifier === null || ! Auth::attempt(['id' => $identifier->account_id, 'password' => $data['password']])) {
            return ApiErrorResponse::make($request, 'INVALID_CREDENTIALS', 'Identifiants invalides.', status: 401);
        }

        $request->session()->regenerate();

        /** @var Account $account */
        $account = Auth::user();
        $this->sessions->startFor($account, $request);

        return new JsonResponse(['id' => $account->id]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accountSession = $this->sessions->findByLaravelSessionId($request->session()->getId());

        if ($accountSession !== null) {
            $this->sessions->revoke($accountSession);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse(status: 204);
    }
}
