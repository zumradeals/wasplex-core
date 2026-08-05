<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LedgerTransaction extends Model
{
    use HasUlids;

    public $timestamps = false;

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_INTERNAL_TEST = 'INTERNAL_TEST';

    public const TYPE_ADMIN_CORRECTION = 'ADMIN_CORRECTION';

    protected $table = 'ledger_transactions';

    protected $fillable = [
        'journal_id', 'type', 'status', 'source_module', 'business_reference',
        'currency', 'rule_version', 'created_by', 'approved_by', 'approved_at',
        'metadata', 'posted_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'posted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'journal_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(LedgerTransactionLink::class, 'transaction_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(LedgerTransactionLink::class, 'linked_transaction_id');
    }
}
