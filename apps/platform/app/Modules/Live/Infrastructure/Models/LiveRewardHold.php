<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardHold extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'live_reward_attention_block_id',
        'live_reward_campaign_id',
        'live_id',
        'account_id',
        'block_index',
        'reward_minor',
        'gross_amount_minor',
        'status',
        'risk_codes',
        'evidence',
        'reviewed_by_account_id',
        'resolution_note',
        'ledger_transaction_id',
        'opened_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'block_index' => 'integer',
            'reward_minor' => 'integer',
            'gross_amount_minor' => 'integer',
            'risk_codes' => 'array',
            'evidence' => 'array',
            'opened_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(LiveRewardAttentionBlock::class, 'live_reward_attention_block_id');
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reviewed_by_account_id');
    }
}
