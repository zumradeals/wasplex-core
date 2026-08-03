<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use App\Modules\Ledger\Domain\Enums\LedgerLinkType;
use App\Modules\Ledger\Infrastructure\Models\Concerns\ImmutableLedgerRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $source_transaction_id
 * @property string $target_transaction_id
 * @property LedgerLinkType $link_type
 * @property CarbonImmutable $created_at
 */
class LedgerTransactionLink extends Model
{
    use HasUlids;
    use ImmutableLedgerRecord;

    public $timestamps = false;

    protected $fillable = ['source_transaction_id', 'target_transaction_id', 'link_type', 'created_at'];

    protected function casts(): array
    {
        return [
            'link_type' => LedgerLinkType::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'source_transaction_id');
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function targetTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'target_transaction_id');
    }
}
