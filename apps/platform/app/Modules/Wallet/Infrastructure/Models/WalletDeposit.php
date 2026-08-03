<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $wallet_id
 * @property string|null $ledger_transaction_id
 * @property string $provider
 * @property string|null $provider_payment_id
 * @property string|null $provider_reference
 * @property string|null $provider_status
 * @property int $amount_minor
 * @property int|null $fee_minor
 * @property string $currency
 * @property DepositStatus $status
 * @property string $idempotency_key
 * @property string|null $checkout_url
 * @property array<string, mixed>|null $provider_metadata
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $confirmed_at
 * @property \Carbon\CarbonImmutable|null $credited_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read Wallet $wallet
 */
class WalletDeposit extends Model
{
    use HasUlids;

    protected $fillable = [
        'wallet_id', 'ledger_transaction_id', 'provider', 'provider_payment_id',
        'provider_reference', 'provider_status', 'amount_minor', 'fee_minor', 'currency',
        'status', 'idempotency_key', 'checkout_url', 'provider_metadata', 'expires_at',
        'confirmed_at', 'credited_at',
    ];

    protected $hidden = ['provider_metadata'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'fee_minor' => 'integer', 'status' => DepositStatus::class,
            'provider_metadata' => 'array', 'expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime', 'credited_at' => 'immutable_datetime',
        ];
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
