<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerIdempotencyKey extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ledger_idempotency_keys';

    protected $fillable = ['idempotency_key', 'content_hash', 'transaction_id', 'source_module'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }
}
