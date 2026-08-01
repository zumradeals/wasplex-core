<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\AccountSessionService;
use App\Modules\Identity\Application\Services\IdentityProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final readonly class RegisterController
{
    public function __construct(
        private IdentityProvisioner $provisioner,
        private AccountSessionService $sessions,
        private AccessAuditor $auditor,
    ) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:account_identifiers,normalized_value'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $account = $this->provisioner->createAccount(
            $validated['name'],
            $validated['email'],
            $validated['password'],
        );

        Auth::login($account);
        $request->session()->regenerate();
        $identitySession = $this->sessions->start($account, $request);
        $request->attributes->set('identity_session', $identitySession);
        $this->auditor->record($request, 'account.registered', 'success');

        if ($request->expectsJson()) {
            return response()->json(['data' => ['account_id' => $account->id]], 201);
        }

        return redirect()->route('space.home');
    }
}
