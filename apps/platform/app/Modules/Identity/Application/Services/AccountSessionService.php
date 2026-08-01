<?php

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AccountSessionService
{
    public function start(Account $account, Request $request): AccountSession
    {
        $userSpace = $account->spaces()->where('kind', SpaceKind::User->value)->firstOrFail();
        $userAgent = (string) $request->userAgent();

        $device = $account->devices()->create([
            'name' => $this->deviceName($userAgent),
            'platform' => $this->platform($userAgent),
            'fingerprint_hash' => hash('sha256', $userAgent),
            'last_seen_at' => now(),
        ]);

        $identitySession = $account->sessions()->create([
            'device_id' => $device->id,
            'active_space_id' => $userSpace->id,
            'session_key_hash' => $this->hash($request->session()->getId()),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit($userAgent, 1000, ''),
            'last_seen_at' => now(),
        ]);

        $request->session()->put('identity_session_id', $identitySession->id);

        return $identitySession;
    }

    public function current(Request $request): ?AccountSession
    {
        $identitySessionId = $request->session()->get('identity_session_id');

        if (! is_string($identitySessionId)) {
            return null;
        }

        $identitySession = AccountSession::query()
            ->with(['activeSpace', 'device'])
            ->whereKey($identitySessionId)
            ->whereNull('revoked_at')
            ->first();

        if ($identitySession !== null) {
            $identitySession->forceFill([
                'session_key_hash' => $this->hash($request->session()->getId()),
            ])->save();
        }

        return $identitySession;
    }

    public function revoke(AccountSession $identitySession): void
    {
        $identitySession->forceFill(['revoked_at' => now()])->save();
    }

    private function hash(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    private function deviceName(string $userAgent): string
    {
        return $userAgent === '' ? 'Appareil inconnu' : Str::limit($userAgent, 120, '');
    }

    private function platform(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
