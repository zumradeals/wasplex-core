<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $wallet_id
 * @property string|null $ledger_transaction_id
 * @property string $type
 * @property string $direction
 * @property string $status
 * @property int $amount_minor
 * @property string $unit
 * @property string $currency
 * @property string $business_reference
 * @property string $idempotency_key
 * @property string $description
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $occurred_at
 */
class WalletOperation extends Model
{
    use HasUlids;

    protected $fillable = [
        'wallet_id', 'ledger_transaction_id', 'type', 'direction', 'status', 'amount_minor',
        'unit', 'currency', 'business_reference', 'idempotency_key', 'description',
        'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Une opération Wallet publiée est immuable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Une opération Wallet publiée est immuable.');
        });
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
