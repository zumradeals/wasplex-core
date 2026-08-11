<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignPublicVisit extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'campaign_version_id',
        'visitor_hash',
        'visit_hash',
        'started_at',
        'completed_at',
        'join_clicked_at',
        'share_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'join_clicked_at' => 'immutable_datetime',
            'share_count' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
