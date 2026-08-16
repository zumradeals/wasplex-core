<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardQuote extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'live_reward_campaign_id',
        'status',
        'currency',
        'budget_amount_minor',
        'wasplex_share_minor',
        'spectator_envelope_minor',
        'estimated_reach_min',
        'estimated_reach_max',
        'planned_duration_minutes',
        'block_duration_seconds',
        'quoted_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount_minor' => 'integer',
            'wasplex_share_minor' => 'integer',
            'spectator_envelope_minor' => 'integer',
            'estimated_reach_min' => 'integer',
            'estimated_reach_max' => 'integer',
            'planned_duration_minutes' => 'integer',
            'block_duration_seconds' => 'integer',
            'quoted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveRewardCampaign::class, 'live_reward_campaign_id');
    }
}
