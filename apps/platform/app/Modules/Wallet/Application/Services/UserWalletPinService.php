<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Infrastructure\Models\UserWallet;
use App\Shared\Http\AppException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Guards Wallet-sensitive operations (docs/CLAUDE.md §8, §15) behind a
 * 4-digit PIN stored hashed on the wallet row. Deliberately generic
 * (`assertValid` takes no operation name) so the same PIN protects
 * transfers today and withdrawals/other sensitive Wallet operations later
 * (P020 §2.6) without a schema change.
 */
final class UserWalletPinService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 300;

    public function hasPin(string $accountId): bool
    {
        return UserWallet::query()
            ->where('account_id', $accountId)
            ->whereNotNull('pin_hash')
            ->exists();
    }

    public function create(string $accountId, string $pin, string $pinConfirmation): void
    {
        $this->assertFourDigits($pin);

        if ($pin !== $pinConfirmation) {
            throw new AppException('WALLET_PIN_CONFIRMATION_MISMATCH', 'La confirmation du code PIN ne correspond pas.');
        }

        $wallet = UserWallet::query()->where('account_id', $accountId)->first();

        if ($wallet !== null && $wallet->pin_hash !== null) {
            throw new AppException(
                'WALLET_PIN_ALREADY_SET',
                'Un code PIN existe déjà pour ce Wallet. Utilisez le changement de PIN.',
                [],
                409,
            );
        }

        UserWallet::query()->updateOrCreate(
            ['account_id' => $accountId],
            ['pin_hash' => Hash::make($pin), 'pin_set_at' => now()],
        );
    }

    public function change(string $accountId, string $currentPin, string $newPin, string $newPinConfirmation): void
    {
        $this->assertFourDigits($newPin);

        if ($newPin !== $newPinConfirmation) {
            throw new AppException('WALLET_PIN_CONFIRMATION_MISMATCH', 'La confirmation du code PIN ne correspond pas.');
        }

        // Reuses the exact same verification path (and rate limiter) as a
        // transfer's PIN check, so a wrong current PIN cannot be probed at
        // a different, unthrottled rate.
        $this->assertValid($accountId, $currentPin);

        UserWallet::query()
            ->where('account_id', $accountId)
            ->update(['pin_hash' => Hash::make($newPin), 'pin_set_at' => now()]);
    }

    /**
     * @throws AppException WALLET_PIN_NOT_SET, WALLET_PIN_REQUIRED,
     *                       WALLET_PIN_LOCKED or WALLET_PIN_INVALID — always
     *                       thrown before the caller opens any Ledger
     *                       write (docs/CLAUDE.md §7, P020 §2.6).
     */
    public function assertValid(string $accountId, ?string $pin): void
    {
        $wallet = UserWallet::query()->where('account_id', $accountId)->first();

        if ($wallet === null || $wallet->pin_hash === null) {
            throw new AppException(
                'WALLET_PIN_NOT_SET',
                'Créez votre code PIN Wallet avant de transférer des WP.',
                [],
                409,
            );
        }

        if ($pin === null || $pin === '') {
            throw new AppException('WALLET_PIN_REQUIRED', 'Le code PIN Wallet est requis pour cette opération.');
        }

        $limiterKey = $this->limiterKey($accountId);

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            throw new AppException(
                'WALLET_PIN_LOCKED',
                'Trop de tentatives. Réessayez dans quelques minutes.',
                ['retry_after_seconds' => RateLimiter::availableIn($limiterKey)],
                423,
            );
        }

        if (! Hash::check($pin, $wallet->pin_hash)) {
            RateLimiter::hit($limiterKey, self::LOCKOUT_SECONDS);

            throw new AppException(
                'WALLET_PIN_INVALID',
                'Le code PIN est incorrect.',
                ['attempts_remaining' => max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($limiterKey))],
            );
        }

        RateLimiter::clear($limiterKey);
    }

    private function assertFourDigits(string $pin): void
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw new AppException('WALLET_PIN_FORMAT_INVALID', 'Le code PIN doit comporter exactement 4 chiffres.');
        }
    }

    private function limiterKey(string $accountId): string
    {
        return "wallet-pin:{$accountId}";
    }
}
