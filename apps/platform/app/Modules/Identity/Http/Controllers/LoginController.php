<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\AccountSessionService;
use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class LoginController
{
    public function __construct(
        private AccountSessionService $sessions,
        private AccessAuditor $auditor,
    ) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identifier = AccountIdentifier::query()
            ->with('account')
            ->where('normalized_value', Str::lower(trim($validated['identifier'])))
            ->first();

        if (
            $identifier === null
            || $identifier->account->status !== AccountStatus::Active
            || ! Hash::check($validated['password'], $identifier->account->password_hash)
        ) {
            throw ValidationException::withMessages([
                'identifier' => 'Identifiant ou mot de passe incorrect.',
            ]);
        }

        $account = $identifier->account;
        Auth::login($account, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();
        $account->forceFill(['last_authenticated_at' => now()])->save();

        $identitySession = $this->sessions->start($account, $request);
        $request->attributes->set('identity_session', $identitySession);
        $this->auditor->record($request, 'session.authenticated', 'success');

        return $request->expectsJson()
            ? response()->json(['data' => ['account_id' => $account->id]])
            : redirect()->intended(route('space.home'));
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $identitySession = $request->attributes->get('identity_session');

        if ($identitySession !== null) {
            $this->auditor->record($request, 'session.logged_out', 'success');
            $this->sessions->revoke($identitySession);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['data' => ['logged_out' => true]])
            : redirect()->route('login');
    }
}
