<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundRehabilitationCase extends Model
{
    use HasUlids;

    public const STATUS_REQUIRED = 'required';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'fund_rehabilitation_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required_actions' => 'array',
            'opened_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(FundMembership::class, 'fund_membership_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
