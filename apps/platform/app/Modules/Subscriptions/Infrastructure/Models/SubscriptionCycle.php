<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SubscriptionCycle extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'subscription_cycles';

    protected $fillable = ['user_subscription_id', 'period_start', 'period_end', 'reset_at'];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'reset_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function quotaCounter(): HasOne
    {
        return $this->hasOne(SubscriptionQuotaCounter::class, 'subscription_cycle_id');
    }
}
