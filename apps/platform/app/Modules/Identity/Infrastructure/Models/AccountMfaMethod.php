<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $account_id
 * @property string $type
 * @property string $secret
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $revoked_at
 */
class AccountMfaMethod extends Model
{
    use HasUlids;

    protected $fillable = ['account_id', 'type', 'secret', 'confirmed_at', 'revoked_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'confirmed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
