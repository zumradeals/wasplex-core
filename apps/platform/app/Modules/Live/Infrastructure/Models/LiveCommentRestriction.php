<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveCommentRestriction extends Model
{
    use HasUlids;

    protected $fillable = [
        'live_id',
        'account_id',
        'is_active',
        'reason',
        'created_by_account_id',
        'lifted_at',
        'lifted_by_account_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lifted_at' => 'datetime',
        ];
    }

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'lifted_by_account_id');
    }
}
