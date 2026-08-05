<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'device_id',
        'laravel_session_id',
        'ip_address',
        'user_agent',
        'mfa_verified_at',
        'last_active_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'mfa_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AccountDevice::class, 'device_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasRecentMfa(int $withinMinutes): bool
    {
        return $this->mfa_verified_at !== null
            && $this->mfa_verified_at->greaterThan(now()->subMinutes($withinMinutes));
    }
}
