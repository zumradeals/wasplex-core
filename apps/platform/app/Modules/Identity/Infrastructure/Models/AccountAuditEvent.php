<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only access/action audit trail.
 * Docs/17-donnees-permissions-consentements-techniques-wasplex.md #35.
 */
final class AccountAuditEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_account_id',
        'actor_space_id',
        'organization_id',
        'capability_code',
        'action',
        'resource_type',
        'resource_id',
        'reason',
        'session_id',
        'device_id',
        'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
