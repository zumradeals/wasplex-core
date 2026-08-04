<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property int $submission_number
 * @property string $status
 * @property string|null $submitted_by_account_id
 * @property string|null $assigned_to_account_id
 * @property string|null $decided_by_account_id
 * @property string|null $decision_reason
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $decided_at
 * @property-read Campaign $campaign
 * @property-read CampaignVersion $campaignVersion
 * @property-read Account|null $submitter
 * @property-read Account|null $assignee
 * @property-read Account|null $decider
 * @property-read CampaignReviewTask|null $task
 * @property-read Collection<int, CampaignReviewEvent> $events
 */
final class CampaignReviewCase extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'campaign_version_id',
        'submission_number',
        'status',
        'submitted_by_account_id',
        'assigned_to_account_id',
        'decided_by_account_id',
        'decision_reason',
        'opened_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_number' => 'integer',
            'opened_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<CampaignVersion, $this> */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class, 'campaign_version_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'submitted_by_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_to_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'decided_by_account_id');
    }

    /** @return HasOne<CampaignReviewTask, $this> */
    public function task(): HasOne
    {
        return $this->hasOne(CampaignReviewTask::class, 'review_case_id');
    }

    /** @return HasMany<CampaignReviewEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CampaignReviewEvent::class, 'review_case_id')->latest('occurred_at');
    }
}
