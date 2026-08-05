<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SpaceMembership extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_space_id',
        'account_id',
        'status',
        'is_default',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'user_space_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
