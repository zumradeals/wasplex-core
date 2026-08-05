<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AccountSessionManager;
use App\Modules\Identity\Application\Services\MfaService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Shared\Http\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeMfaController extends Controller
{
    public function __construct(
        private readonly MfaService $mfa,
        private readonly AccountSessionManager $sessions,
    ) {}

    /**
     * Begin TOTP enrollment. The secret is only ever returned here.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return new JsonResponse($this->mfa->beginEnrollment($account));
    }

    /**
     * Confirm enrollment with a first valid code.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        /** @var Account $account */
        $account = $request->user();

        if (! $this->mfa->confirmEnrollment($account, $data['code'])) {
            return ApiErrorResponse::make($request, 'MFA_CODE_INVALID', 'Code invalide.', status: 422);
        }

        return new JsonResponse(['mfa_enabled' => true]);
    }

    /**
     * Verify a code to elevate the current session's MFA freshness
     * (required before entering the admin space).
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        /** @var Account $account */
        $account = $request->user();

        if (! $this->mfa->isEnabled($account) || ! $this->mfa->verifyCode($account, $data['code'])) {
            return ApiErrorResponse::make($request, 'MFA_CODE_INVALID', 'Code invalide.', status: 422);
        }

        /** @var AccountSession $accountSession */
        $accountSession = $request->attributes->get('account_session');
        $this->sessions->markMfaVerified($accountSession);

        return new JsonResponse(['mfa_verified' => true]);
    }
}
