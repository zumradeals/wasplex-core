<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FundCollectionParticipant extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_ARREARS = 'arrears';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'fund_collection_participants';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rule_snapshot' => 'array',
            'last_attempted_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(FundCollectionSnapshot::class, 'snapshot_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(FundMembership::class, 'fund_membership_id');
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(FundMandate::class, 'fund_mandate_id');
    }

    public function debits(): HasMany
    {
        return $this->hasMany(FundCollectionDebit::class, 'participant_id');
    }

    public function arrear(): HasOne
    {
        return $this->hasOne(FundArrear::class, 'participant_id');
    }
}
