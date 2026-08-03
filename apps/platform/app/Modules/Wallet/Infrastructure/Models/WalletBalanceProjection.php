<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $wallet_id
 * @property int $available_minor
 * @property int $reserved_minor
 * @property int $pending_minor
 * @property int $version
 * @property string|null $last_ledger_transaction_id
 * @property CarbonImmutable|null $rebuilt_at
 * @property-read Wallet $wallet
 */
class WalletBalanceProjection extends Model
{
    use HasUlids;

    protected $fillable = [
        'wallet_id', 'available_minor', 'reserved_minor', 'pending_minor', 'version',
        'last_ledger_transaction_id', 'rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'available_minor' => 'integer', 'reserved_minor' => 'integer',
            'pending_minor' => 'integer', 'version' => 'integer', 'rebuilt_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function lastLedgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'last_ledger_transaction_id');
    }
}
