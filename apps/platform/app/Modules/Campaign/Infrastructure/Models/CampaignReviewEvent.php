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
 * @property string|null $actor_account_id
 * @property string $type
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $occurred_at
 * @property-read CampaignReviewCase $reviewCase
 * @property-read Campaign $campaign
 * @property-read Account|null $actor
 */
final class CampaignReviewEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'review_case_id',
        'campaign_id',
        'actor_account_id',
        'type',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }
}
