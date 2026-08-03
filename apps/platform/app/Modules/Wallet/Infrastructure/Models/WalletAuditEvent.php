<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $wallet_id
 * @property string|null $actor_account_id
 * @property string|null $actor_space_id
 * @property string $action
 * @property string $outcome
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $trace_id
 * @property array<string, mixed>|null $context
 * @property \Carbon\CarbonImmutable $occurred_at
 */
class WalletAuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'wallet_id', 'actor_account_id', 'actor_space_id', 'action', 'outcome',
        'subject_type', 'subject_id', 'trace_id', 'context', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['context' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Un événement d’audit Wallet est immuable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Un événement d’audit Wallet est immuable.');
        });
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function actorAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }

    /** @return BelongsTo<UserSpace, $this> */
    public function actorSpace(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'actor_space_id');
    }
}
