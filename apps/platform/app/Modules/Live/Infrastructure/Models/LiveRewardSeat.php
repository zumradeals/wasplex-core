<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardSeat extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'live_reward_campaign_id',
        'live_id',
        'account_id',
        'viewer_session_id',
        'economic_class',
        'status',
        'activated_at',
        'released_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'released_at' => 'datetime',
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

    public function viewerSession(): BelongsTo
    {
        return $this->belongsTo(LiveViewerSession::class, 'viewer_session_id');
    }
}
