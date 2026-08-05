<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountDevice;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Http\Request;

/**
 * An application session (account_sessions) is distinct from the underlying
 * Laravel session: revoking one does not require destroying the Laravel
 * session store entry, it only marks our row so EnsureSessionNotRevoked
 * rejects the very next request carrying that Laravel session id.
 */
final class AccountSessionManager
{
    public function startFor(Account $account, Request $request): AccountSession
    {
        $device = $this->rememberDevice($account, $request);

        return AccountSession::updateOrCreate(
            ['laravel_session_id' => $request->session()->getId()],
            [
                'account_id' => $account->id,
                'device_id' => $device->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'last_active_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    public function touch(AccountSession $session): void
    {
        $session->update(['last_active_at' => now()]);
    }

    public function revoke(AccountSession $session): void
    {
        $session->update(['revoked_at' => now()]);
    }

    public function markMfaVerified(AccountSession $session): void
    {
        $session->update(['mfa_verified_at' => now()]);
    }

    public function findByLaravelSessionId(string $laravelSessionId): ?AccountSession
    {
        return AccountSession::query()
            ->where('laravel_session_id', $laravelSessionId)
            ->first();
    }

    private function rememberDevice(Account $account, Request $request): AccountDevice
    {
        $fingerprint = sha1($request->userAgent().'|'.$request->ip());

        $device = AccountDevice::query()
            ->where('account_id', $account->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($device !== null) {
            $device->update(['last_ip' => $request->ip(), 'last_seen_at' => now()]);

            return $device;
        }

        return AccountDevice::create([
            'account_id' => $account->id,
            'fingerprint' => $fingerprint,
            'user_agent' => (string) $request->userAgent(),
            'status' => 'new',
            'last_ip' => $request->ip(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
