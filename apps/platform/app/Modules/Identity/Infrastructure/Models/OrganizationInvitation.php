<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $invited_by_account_id
 * @property string $identifier
 * @property string $token_hash
 * @property string $status
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 */
class OrganizationInvitation extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'invited_by_account_id',
        'identifier',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
