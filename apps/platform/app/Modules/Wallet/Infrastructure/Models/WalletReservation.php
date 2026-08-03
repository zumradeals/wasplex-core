<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $wallet_id
 * @property string|null $reserve_ledger_transaction_id
 * @property string|null $resolution_ledger_transaction_id
 * @property string $purpose
 * @property int $amount_minor
 * @property string $unit
 * @property string $currency
 * @property ReservationStatus $status
 * @property string $business_reference
 * @property string $idempotency_key
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $resolved_at
 * @property array<string, mixed>|null $metadata
 */
class WalletReservation extends Model
{
    use HasUlids;

    protected $fillable = [
        'wallet_id', 'reserve_ledger_transaction_id', 'resolution_ledger_transaction_id',
        'purpose', 'amount_minor', 'unit', 'currency', 'status', 'business_reference',
        'idempotency_key', 'expires_at', 'resolved_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'status' => ReservationStatus::class,
            'expires_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime', 'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function reserveLedgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'reserve_ledger_transaction_id');
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function resolutionLedgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'resolution_ledger_transaction_id');
    }
}
