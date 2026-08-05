<?php

namespace App\Modules\Distribution\Infrastructure\Models;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Distribution\Domain\Enums\DeliveryLeaseStatus;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $feed_session_id
 * @property string $account_id
 * @property string $advertising_match_id
 * @property string $idempotency_key
 * @property DeliveryLeaseStatus $status
 * @property CarbonImmutable $reserved_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $released_at
 */
final class AdvertisingDeliveryLease extends Model
{
    use HasUlids;

    protected $fillable = [
        'feed_session_id',
        'account_id',
        'advertising_match_id',
        'idempotency_key',
        'status',
        'reserved_at',
        'expires_at',
        'delivered_at',
        'released_at',
    ];

    protected $hidden = ['account_id', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryLeaseStatus::class,
            'reserved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FeedSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedSession::class, 'feed_session_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AdvertisingMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(AdvertisingMatch::class, 'advertising_match_id');
    }
}
