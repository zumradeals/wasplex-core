<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Models\Concerns\ImmutableLedgerRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $transaction_id
 * @property string $account_id
 * @property EntryDirection $direction
 * @property int $amount_minor
 * @property string $unit
 * @property string $currency
 * @property string $description
 * @property string|null $external_reference
 * @property CarbonImmutable $posted_at
 * @property CarbonImmutable $created_at
 * @property-read LedgerTransaction $transaction
 * @property-read LedgerAccount $account
 */
class LedgerEntry extends Model
{
    use HasUlids;
    use ImmutableLedgerRecord;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'account_id',
        'direction',
        'amount_minor',
        'unit',
        'currency',
        'description',
        'external_reference',
        'posted_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => EntryDirection::class,
            'amount_minor' => 'integer',
            'posted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}
