<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveCommentReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'live_id',
        'comment_id',
        'reporter_account_id',
        'category',
        'note',
    ];

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(LiveComment::class, 'comment_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reporter_account_id');
    }
}
