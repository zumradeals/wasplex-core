<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionPlan extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'subscription_plans';

    protected $fillable = ['code', 'name'];

    public function versions(): HasMany
    {
        return $this->hasMany(SubscriptionPlanVersion::class, 'plan_id');
    }
}
