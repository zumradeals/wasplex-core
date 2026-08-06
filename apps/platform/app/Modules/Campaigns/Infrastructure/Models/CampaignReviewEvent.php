<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignReviewEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const TYPE_SUBMITTED = 'submitted';

    public const TYPE_CHANGES_REQUESTED = 'changes_requested';

    public const TYPE_RESUBMITTED = 'resubmitted';

    public const TYPE_APPROVED = 'approved';

    public const TYPE_REJECTED = 'rejected';

    public const TYPE_SUSPENDED = 'suspended';

    protected $fillable = ['campaign_review_case_id', 'event_type', 'actor_account_id', 'reason'];

    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(CampaignReviewCase::class, 'campaign_review_case_id');
    }
}
