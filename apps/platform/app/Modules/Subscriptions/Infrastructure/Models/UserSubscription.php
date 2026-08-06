<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UserSubscription extends Model
{
    use HasUlids;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SCHEDULED_DOWNGRADE = 'scheduled_downgrade';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'user_subscriptions';

    protected $fillable = [
        'account_id', 'plan_version_id', 'economic_class_id', 'scheduled_plan_version_id',
        'status', 'started_at', 'current_period_end', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanVersion::class, 'plan_version_id');
    }

    public function scheduledPlanVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanVersion::class, 'scheduled_plan_version_id');
    }

    public function economicClass(): BelongsTo
    {
        return $this->belongsTo(EconomicClass::class, 'economic_class_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(SubscriptionCycle::class, 'user_subscription_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class, 'user_subscription_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(SubscriptionEntitlement::class, 'user_subscription_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_SCHEDULED_DOWNGRADE], true);
    }
}
