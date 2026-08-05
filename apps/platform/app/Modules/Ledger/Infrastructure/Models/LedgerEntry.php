<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerEntry extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const DIRECTION_DEBIT = 'debit';

    public const DIRECTION_CREDIT = 'credit';

    protected $table = 'ledger_entries';

    protected $fillable = [
        'transaction_id', 'account_id', 'direction', 'amount_minor',
        'currency', 'description', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'posted_at' => 'immutable_datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}
