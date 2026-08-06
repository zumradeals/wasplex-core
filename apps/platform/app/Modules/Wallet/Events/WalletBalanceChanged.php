<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * docs/07 §23 : `wallet.balance.changed` diffusé sur le canal privé du
 * compte concerné. Diffusion best-effort — le Grand Livre est déjà commité
 * avant que cet événement soit levé (docs/chantiers/P011-CHANTIER.md §2.4) ;
 * une perte de diffusion ne remet jamais en cause le solde réel.
 */
final class WalletBalanceChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $accountId,
        public readonly int $amountMinor,
        public readonly int $balanceMinor,
        public readonly string $origin,
        public readonly string $operation,
        public readonly string $ledgerTransactionId,
    ) {
        $this->occurredAt = Carbon::now('UTC')->toIso8601String();
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("wallet.{$this->accountId}")];
    }

    public function broadcastAs(): string
    {
        return 'wallet.balance.changed';
    }

    /**
     * @return array<string, int|string>
     */
    public function broadcastWith(): array
    {
        return [
            'amount_minor' => $this->amountMinor,
            'balance_minor' => $this->balanceMinor,
            'origin' => $this->origin,
            'operation' => $this->operation,
            'ledger_transaction_id' => $this->ledgerTransactionId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
