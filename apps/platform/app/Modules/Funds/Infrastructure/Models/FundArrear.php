<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundArrear extends Model
{
    use HasUlids;

    public const STATUS_OPEN = 'open';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_WAIVED = 'waived';

    protected $table = 'fund_arrears';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'grace_ends_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
