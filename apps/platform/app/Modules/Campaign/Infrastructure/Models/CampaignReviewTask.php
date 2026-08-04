<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $review_case_id
 * @property string $campaign_id
 * @property string $status
 * @property string $priority
 * @property string|null $assigned_to_account_id
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $completed_at
 * @property-read CampaignReviewCase $reviewCase
 * @property-read Campaign $campaign
 * @property-read Account|null $assignee
 */
final class CampaignReviewTask extends Model
{
    use HasUlids;

    protected $fillable = [
        'review_case_id',
        'campaign_id',
        'status',
        'priority',
        'assigned_to_account_id',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CampaignReviewCase, $this> */
    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(CampaignReviewCase::class, 'review_case_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_to_account_id');
    }
}
