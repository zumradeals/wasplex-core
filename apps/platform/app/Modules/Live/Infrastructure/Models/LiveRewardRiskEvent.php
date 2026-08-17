<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountDevice;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardRiskEvent extends Model
{
    use HasUlids;

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'live_reward_campaign_id',
        'live_id',
        'account_id',
        'live_reward_attention_state_id',
        'account_session_id',
        'device_id',
        'signal_code',
        'severity',
        'mode',
        'evidence',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'occurred_at' => 'datetime',
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

    public function attentionState(): BelongsTo
    {
        return $this->belongsTo(LiveRewardAttentionState::class, 'live_reward_attention_state_id');
    }

    public function accountSession(): BelongsTo
    {
        return $this->belongsTo(AccountSession::class, 'account_session_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AccountDevice::class, 'device_id');
    }
}
