<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionPayment extends Model
{
    use HasUlids;

    public const STATUS_CREATED = 'created';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    /** @var array<int, string> */
    public const TERMINAL_STATUSES = [self::STATUS_ACTIVATED, self::STATUS_REJECTED, self::STATUS_EXPIRED];

    protected $table = 'subscription_payments';

    protected $fillable = [
        'account_id', 'plan_version_id', 'user_subscription_id', 'amount_minor', 'currency',
        'status', 'provider_code', 'provider_reference', 'checkout_url', 'idempotency_key', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanVersion::class, 'plan_version_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
