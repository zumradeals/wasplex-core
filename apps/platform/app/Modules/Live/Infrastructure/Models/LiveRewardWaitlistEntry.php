<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardWaitlistEntry extends Model
{
    use HasUlids;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_OFFERED = 'offered';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'live_reward_campaign_id',
        'live_id',
        'account_id',
        'economic_class',
        'status',
        'queued_at',
        'offered_at',
        'offer_expires_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'resolved_at' => 'datetime',
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
}
