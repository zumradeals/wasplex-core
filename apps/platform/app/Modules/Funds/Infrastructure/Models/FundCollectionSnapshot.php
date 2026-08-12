<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FundCollectionSnapshot extends Model
{
    use HasUlids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_PARTIALLY_FUNDED = 'partially_funded';

    public const STATUS_FUNDED = 'funded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'fund_collection_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rules_snapshot' => 'array',
            'notice_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundProgram::class, 'fund_program_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FundProgramVersion::class, 'program_version_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FundCollectionParticipant::class, 'snapshot_id')->orderBy('ordinal');
    }

    public function debits(): HasMany
    {
        return $this->hasMany(FundCollectionDebit::class, 'snapshot_id');
    }
}
