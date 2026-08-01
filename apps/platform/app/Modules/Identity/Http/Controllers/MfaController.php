<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\TotpService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountMfaMethod;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class MfaController
{
    public function __construct(
        private TotpService $totp,
        private AccessAuditor $auditor,
    ) {}

    public function setupPage(Request $request): Response
    {
        /** @var Account $account */
        $account = $request->user();
        $method = AccountMfaMethod::query()
            ->where('account_id', $account->id)
            ->whereNull('revoked_at')
            ->latest()
            ->first();

        $identifier = $account->identifiers()->where('is_primary', true)->value('display_value') ?? $account->id;

        return Inertia::render('Security/MfaSetup', [
            'configured' => $method?->confirmed_at !== null,
            'secret' => $method !== null && $method->confirmed_at === null ? $method->secret : null,
            'uri' => $method !== null && $method->confirmed_at === null
                ? $this->totp->uri($method->secret, $identifier)
                : null,
        ]);
    }

    public function begin(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $confirmed = AccountMfaMethod::query()
            ->where('account_id', $account->id)
            ->whereNotNull('confirmed_at')
            ->whereNull('revoked_at')
            ->exists();

        if ($confirmed) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Le MFA est déjà configuré.'], 409)
                : redirect()->route('security.mfa.setup');
        }

        $method = AccountMfaMethod::query()->updateOrCreate(
            ['account_id' => $account->id, 'type' => 'totp', 'confirmed_at' => null, 'revoked_at' => null],
            ['secret' => $this->totp->generateSecret()],
        );

        $identifier = $account->identifiers()->where('is_primary', true)->value('display_value') ?? $account->id;
        $this->auditor->record($request, 'mfa.enrollment_started', 'success');

        return $request->expectsJson()
            ? response()->json(['data' => ['secret' => $method->secret, 'uri' => $this->totp->uri($method->secret, $identifier)]])
            : redirect()->route('security.mfa.setup');
    }

    public function confirm(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $method = $this->pendingMethod($request);

        if (! $this->totp->verify($method->secret, $validated['code'])) {
            $this->auditor->record($request, 'mfa.enrollment_confirmed', 'denied');

            return back()->withErrors(['code' => 'Code de vérification incorrect.']);
        }

        $method->forceFill(['confirmed_at' => now()])->save();
        $this->markSessionVerified($request);
        $this->auditor->record($request, 'mfa.enrollment_confirmed', 'success');

        return $request->expectsJson()
            ? response()->json(['data' => ['confirmed' => true]])
            : redirect()->route('space.home');
    }

    public function challengePage(): Response
    {
        return Inertia::render('Security/MfaChallenge');
    }

    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var Account $account */
        $account = $request->user();
        $method = AccountMfaMethod::query()
            ->where('account_id', $account->id)
            ->whereNotNull('confirmed_at')
            ->whereNull('revoked_at')
            ->latest('confirmed_at')
            ->first();

        if ($method === null || ! $this->totp->verify($method->secret, $validated['code'])) {
            $this->auditor->record($request, 'mfa.challenge', 'denied');

            return back()->withErrors(['code' => 'Code de vérification incorrect.']);
        }

        $this->markSessionVerified($request);
        $this->auditor->record($request, 'mfa.challenge', 'success');

        return $request->expectsJson()
            ? response()->json(['data' => ['verified' => true]])
            : redirect()->intended(route('space.home'));
    }

    private function pendingMethod(Request $request): AccountMfaMethod
    {
        return AccountMfaMethod::query()
            ->where('account_id', $request->user()?->getAuthIdentifier())
            ->whereNull('confirmed_at')
            ->whereNull('revoked_at')
            ->latest()
            ->firstOrFail();
    }

    private function markSessionVerified(Request $request): void
    {
        $identitySession = $request->attributes->get('identity_session');

        if ($identitySession instanceof AccountSession) {
            $identitySession->forceFill(['mfa_verified_at' => now()])->save();
        }
    }
}
