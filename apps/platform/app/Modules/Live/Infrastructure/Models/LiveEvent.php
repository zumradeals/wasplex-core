<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LiveEvent extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    protected $table = 'lives';

    protected $fillable = [
        'owner_account_id',
        'advertiser_organization_id',
        'title',
        'description',
        'category',
        'language',
        'visibility',
        'status',
        'scheduled_at',
        'planned_duration_minutes',
        'started_at',
        'ended_at',
        'replay_policy',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'planned_duration_minutes' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'owner_account_id');
    }

    public function advertiserOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'advertiser_organization_id');
    }

    public function streamSessions(): HasMany
    {
        return $this->hasMany(LiveStreamSession::class, 'live_id');
    }

    public function viewerSessions(): HasMany
    {
        return $this->hasMany(LiveViewerSession::class, 'live_id');
    }

    public function stageRequests(): HasMany
    {
        return $this->hasMany(LiveStageRequest::class, 'live_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(LiveAuditEvent::class, 'live_id');
    }
}
