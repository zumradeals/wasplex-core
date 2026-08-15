<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveViewerSession extends Model
{
    use HasUlids;

    public const STATUS_WATCHING = 'watching';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_LEFT = 'left';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'live_id',
        'account_id',
        'status',
        'joined_at',
        'last_seen_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
