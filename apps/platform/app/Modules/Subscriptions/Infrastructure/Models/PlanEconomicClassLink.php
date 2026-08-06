<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanEconomicClassLink extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'plan_economic_class_links';

    protected $fillable = ['plan_version_id', 'economic_class_id'];

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanVersion::class, 'plan_version_id');
    }

    public function economicClass(): BelongsTo
    {
        return $this->belongsTo(EconomicClass::class, 'economic_class_id');
    }
}
