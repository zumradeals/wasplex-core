<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundCollectionDebit extends Model
{
    use HasUlids;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $table = 'fund_collection_debits';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'immutable_datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(FundCollectionSnapshot::class, 'snapshot_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FundCollectionParticipant::class, 'participant_id');
    }
}
