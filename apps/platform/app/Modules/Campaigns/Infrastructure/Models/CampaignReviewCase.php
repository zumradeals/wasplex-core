<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CampaignReviewCase extends Model
{
    use HasUlids;

    public const STATUS_OPEN = 'open';

    public const STATUS_DECIDED = 'decided';

    public const DECISION_APPROVED = 'approved';

    public const DECISION_CHANGES_REQUESTED = 'changes_requested';

    public const DECISION_REJECTED = 'rejected';

    protected $fillable = [
        'campaign_id', 'campaign_version_id', 'status', 'decision', 'reason',
        'requested_changes_by', 'decided_by', 'opened_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignReviewEvent::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
