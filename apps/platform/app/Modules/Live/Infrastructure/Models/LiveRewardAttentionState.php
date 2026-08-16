<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardAttentionState extends Model
{
    use HasUlids;

    protected $fillable = [
        'live_reward_campaign_id',
        'live_id',
        'account_id',
        'live_reward_seat_id',
        'viewer_session_id',
        'validated_blocks',
        'current_block_ms',
        'risk_signal_count',
        'last_heartbeat_qualified',
        'last_risk_signal_code',
        'last_heartbeat_at',
        'last_qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_blocks' => 'integer',
            'current_block_ms' => 'integer',
            'risk_signal_count' => 'integer',
            'last_heartbeat_qualified' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'last_qualified_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveRewardCampaign::class, 'live_reward_campaign_id');
    }

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(LiveRewardSeat::class, 'live_reward_seat_id');
    }

    public function viewerSession(): BelongsTo
    {
        return $this->belongsTo(LiveViewerSession::class, 'viewer_session_id');
    }
}
