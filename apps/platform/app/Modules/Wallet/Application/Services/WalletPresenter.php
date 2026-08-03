<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\WalletOperation;
use LogicException;

final class WalletPresenter
{
    /** @return array<string, mixed> */
    public function wallet(Wallet $wallet): array
    {
        $wallet->loadMissing('projection');
        $projection = $wallet->projection;

        if ($projection === null) {
            throw new LogicException('La projection comptable du Wallet est absente.');
        }

        return [
            'id' => $wallet->id,
            'kind' => $wallet->kind->value,
            'unit' => $wallet->unit,
            'currency' => $wallet->currency,
            'status' => $wallet->status->value,
            'balances' => [
                'available' => $projection->available_minor,
                'reserved' => $projection->reserved_minor,
                'pending' => $projection->pending_minor,
                'total' => $projection->available_minor
                    + $projection->reserved_minor
                    + $projection->pending_minor,
                'version' => $projection->version,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function operation(WalletOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'type' => $operation->type,
            'direction' => $operation->direction,
            'status' => $operation->status,
            'amount' => $operation->amount_minor,
            'unit' => $operation->unit,
            'currency' => $operation->currency,
            'reference' => $operation->business_reference,
            'description' => $operation->description,
            'occurredAt' => $operation->occurred_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function deposit(WalletDeposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'provider' => $deposit->provider,
            'reference' => $deposit->provider_reference,
            'amount' => $deposit->amount_minor,
            'fee' => $deposit->fee_minor,
            'currency' => $deposit->currency,
            'status' => $deposit->status->value,
            'providerStatus' => $deposit->provider_status,
            'checkoutUrl' => $deposit->status->isTerminal() ? null : $deposit->checkout_url,
            'createdAt' => $deposit->created_at?->toIso8601String(),
            'creditedAt' => $deposit->credited_at?->toIso8601String(),
        ];
    }
}
