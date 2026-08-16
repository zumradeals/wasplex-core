<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveInteractionSetting extends Model
{
    protected $primaryKey = 'live_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'live_id',
        'comments_enabled',
        'slow_mode_seconds',
        'updated_by_account_id',
    ];

    protected function casts(): array
    {
        return [
            'comments_enabled' => 'boolean',
            'slow_mode_seconds' => 'integer',
        ];
    }

    public function live(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'updated_by_account_id');
    }
}
