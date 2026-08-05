<?php

namespace App\Modules\Distribution\Infrastructure\Models;

use App\Modules\Distribution\Domain\Enums\FeedSessionStatus;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $user_subscription_id
 * @property string $subscription_quota_counter_id
 * @property string $economic_class_id
 * @property string $economic_class_version_id
 * @property string $idempotency_key
 * @property FeedSessionStatus $status
 * @property string $market
 * @property string $currency
 * @property string|null $territory
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $closed_at
 */
final class FeedSession extends Model
{
    use HasUlids;

    protected $table = 'advertising_feed_sessions';

    protected $fillable = [
        'account_id',
        'user_subscription_id',
        'subscription_quota_counter_id',
        'economic_class_id',
        'economic_class_version_id',
        'idempotency_key',
        'status',
        'market',
        'currency',
        'territory',
        'started_at',
        'expires_at',
        'closed_at',
    ];

    protected $hidden = ['account_id', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'status' => FeedSessionStatus::class,
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
