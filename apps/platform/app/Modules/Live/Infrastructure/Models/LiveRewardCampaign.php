<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LiveRewardCampaign extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_FUNDS_RESERVED = 'funds_reserved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'live_id',
        'advertiser_organization_id',
        'created_by_account_id',
        'status',
        'segment_configuration',
    ];

    protected function casts(): array
    {
        return [
            'segment_configuration' => 'array',
        ];
    }

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function advertiserOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'advertiser_organization_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(LiveRewardQuote::class, 'live_reward_campaign_id');
    }

    public function budgetReservations(): HasMany
    {
        return $this->hasMany(LiveRewardBudgetReservation::class, 'live_reward_campaign_id');
    }

    public function rewardSeats(): HasMany
    {
        return $this->hasMany(LiveRewardSeat::class, 'live_reward_campaign_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(LiveRewardWaitlistEntry::class, 'live_reward_campaign_id');
    }
}
