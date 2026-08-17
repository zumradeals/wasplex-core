<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class LiveRewardAttentionBlock extends Model
{
    use HasUlids;

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_HELD = 'held';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'live_reward_campaign_id',
        'live_reward_quote_id',
        'live_id',
        'account_id',
        'live_reward_seat_id',
        'viewer_session_id',
        'block_index',
        'attention_ms',
        'reward_minor',
        'gross_amount_minor',
        'risk_mode',
        'status',
        'ledger_transaction_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'block_index' => 'integer',
            'attention_ms' => 'integer',
            'reward_minor' => 'integer',
            'gross_amount_minor' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveRewardCampaign::class, 'live_reward_campaign_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(LiveRewardQuote::class, 'live_reward_quote_id');
    }

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function hold(): HasOne
    {
        return $this->hasOne(LiveRewardHold::class, 'live_reward_attention_block_id');
    }
}
