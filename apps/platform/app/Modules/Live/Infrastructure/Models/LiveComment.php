<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveComment extends Model
{
    use HasUlids;

    protected $fillable = [
        'live_id',
        'account_id',
        'parent_comment_id',
        'body',
        'pinned_at',
        'pinned_by_account_id',
        'hidden_at',
        'hidden_by_account_id',
    ];

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'hidden_at' => 'datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'pinned_by_account_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'hidden_by_account_id');
    }
}
