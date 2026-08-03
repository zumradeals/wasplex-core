<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $source_module
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string|null $transaction_id
 * @property CarbonImmutable $created_at
 */
class LedgerIdempotencyKey extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'source_module',
        'idempotency_key',
        'payload_hash',
        'transaction_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $key): void {
            $dirty = array_keys($key->getDirty());

            if ($key->getRawOriginal('transaction_id') === null && $key->transaction_id !== null && $dirty === ['transaction_id']) {
                return;
            }

            throw new \LogicException('Une clé d’idempotence consommée ne peut pas être modifiée.');
        });

        static::deleting(static function (): never {
            throw new \LogicException('Une clé d’idempotence du Grand Livre ne peut pas être supprimée.');
        });
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }
}
