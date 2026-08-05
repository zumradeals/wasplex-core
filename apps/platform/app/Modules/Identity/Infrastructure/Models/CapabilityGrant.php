<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CapabilityGrant extends Model
{
    use HasUlids;

    public const SCOPE_ORGANIZATION = 'organization';

    public const SCOPE_SPACE = 'space';

    protected $fillable = [
        'account_id',
        'capability_code',
        'scope_type',
        'scope_id',
        'status',
        'starts_at',
        'expires_at',
        'granted_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'granted_by');
    }

    public function isActiveAt(\DateTimeInterface $now): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at->greaterThan($now)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($now)) {
            return false;
        }

        return true;
    }

    public function matchesScope(?string $scopeType, ?string $scopeId): bool
    {
        if ($this->scope_type === null) {
            return true;
        }

        return $this->scope_type === $scopeType && $this->scope_id === $scopeId;
    }
}
