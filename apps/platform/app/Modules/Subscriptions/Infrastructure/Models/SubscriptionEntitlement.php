<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionEntitlement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const KEY_FONDS_ELIGIBLE = 'fonds.eligible';

    protected $table = 'subscription_entitlements';

    protected $fillable = ['user_subscription_id', 'key', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }
}
