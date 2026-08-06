<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionQuotaCounter extends Model
{
    use HasUlids;

    protected $table = 'subscription_quota_counters';

    protected $fillable = ['subscription_cycle_id', 'quota_allocated', 'quota_consumed'];

    protected function casts(): array
    {
        return [
            'quota_allocated' => 'integer',
            'quota_consumed' => 'integer',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class, 'subscription_cycle_id');
    }

    public function remaining(): int
    {
        return $this->quota_allocated - $this->quota_consumed;
    }
}
