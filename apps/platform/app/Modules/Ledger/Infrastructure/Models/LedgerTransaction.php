<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use App\Modules\Ledger\Domain\Enums\LedgerTransactionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $journal_id
 * @property string $type
 * @property LedgerTransactionStatus $status
 * @property string $source_module
 * @property string $business_reference
 * @property string $idempotency_key
 * @property string|null $rule_version
 * @property string $unit
 * @property string $currency
 * @property int $total_minor
 * @property string|null $created_by_account_id
 * @property string|null $beneficiary_account_id
 * @property string|null $justification
 * @property array<string, mixed>|null $metadata
 * @property string|null $trace_id
 * @property CarbonImmutable $posted_at
 * @property CarbonImmutable $created_at
 * @property-read LedgerJournal $journal
 * @property-read Collection<int, LedgerEntry> $entries
 * @property-read Collection<int, LedgerTransactionLink> $outgoingLinks
 * @property-read Collection<int, LedgerTransactionLink> $incomingLinks
 */
class LedgerTransaction extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'journal_id',
        'type',
        'status',
        'source_module',
        'business_reference',
        'idempotency_key',
        'rule_version',
        'unit',
        'currency',
        'total_minor',
        'created_by_account_id',
        'beneficiary_account_id',
        'justification',
        'metadata',
        'trace_id',
        'posted_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LedgerTransactionStatus::class,
            'total_minor' => 'integer',
            'metadata' => 'array',
            'posted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $transaction): void {
            $originalStatus = $transaction->getRawOriginal('status');
            $dirty = array_keys($transaction->getDirty());

            if (
                $originalStatus === LedgerTransactionStatus::Draft->value
                && $transaction->status === LedgerTransactionStatus::Posted
                && $dirty === ['status']
            ) {
                return;
            }

            throw new \LogicException('Une transaction publiée du Grand Livre ne peut pas être modifiée.');
        });

        static::deleting(static function (): never {
            throw new \LogicException('Une transaction publiée du Grand Livre ne peut pas être supprimée.');
        });
    }

    /** @return BelongsTo<LedgerJournal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'journal_id');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }

    /** @return HasMany<LedgerTransactionLink, $this> */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(LedgerTransactionLink::class, 'source_transaction_id');
    }

    /** @return HasMany<LedgerTransactionLink, $this> */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(LedgerTransactionLink::class, 'target_transaction_id');
    }
}
