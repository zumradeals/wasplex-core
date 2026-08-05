<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerTransactionLink extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const LINK_TYPE_REVERSAL = 'reversal';

    protected $table = 'ledger_transaction_links';

    protected $fillable = ['transaction_id', 'linked_transaction_id', 'link_type'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'linked_transaction_id');
    }
}
