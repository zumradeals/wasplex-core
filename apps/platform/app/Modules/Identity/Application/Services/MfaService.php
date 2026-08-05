<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use PragmaRX\Google2FA\Google2FA;

/**
 * Built-in TOTP enrollment/verification (docs/chantiers/P001-CHANTIER.md:
 * no external MFA provider, no recovery codes for P001). The secret is
 * encrypted at rest (Account::$casts) and never re-exposed once enrolled.
 */
final class MfaService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function beginEnrollment(Account $account): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $account->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => null])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $account->primaryIdentifierLabel(),
                $secret,
            ),
        ];
    }

    public function confirmEnrollment(Account $account, string $code): bool
    {
        if ($account->totp_secret === null) {
            return false;
        }

        if (! $this->verifyCode($account, $code)) {
            return false;
        }

        $account->forceFill(['totp_enabled_at' => now()])->save();

        return true;
    }

    public function verifyCode(Account $account, string $code): bool
    {
        if ($account->totp_secret === null) {
            return false;
        }

        return $this->google2fa->verifyKey($account->totp_secret, $code);
    }

    public function isEnabled(Account $account): bool
    {
        return $account->totp_enabled_at !== null;
    }
}
