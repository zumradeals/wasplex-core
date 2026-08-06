<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const TYPE_SELECTED = 'SubscriptionSelected';

    public const TYPE_PAYMENT_RECEIVED = 'SubscriptionPaymentReceived';

    public const TYPE_ACTIVATED = 'SubscriptionActivated';

    public const TYPE_RENEWED = 'SubscriptionRenewed';

    public const TYPE_UPGRADED = 'SubscriptionUpgraded';

    public const TYPE_DOWNGRADE_SCHEDULED = 'SubscriptionDowngradeScheduled';

    public const TYPE_EXPIRED = 'SubscriptionExpired';

    public const TYPE_SUSPENDED = 'SubscriptionSuspended';

    public const TYPE_REFUNDED = 'SubscriptionRefunded';

    public const TYPE_QUOTA_CONSUMED = 'AdQuotaConsumed';

    public const TYPE_QUOTA_RESTORED = 'AdQuotaRestored';

    public const TYPE_QUOTA_RESET = 'AdQuotaReset';

    protected $table = 'subscription_events';

    protected $fillable = ['user_subscription_id', 'event_type', 'payload', 'idempotency_key'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }
}
