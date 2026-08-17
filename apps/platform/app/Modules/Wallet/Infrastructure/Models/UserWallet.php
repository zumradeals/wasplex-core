<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class UserWallet extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'user_wallets';

    protected $fillable = ['account_id', 'currency', 'status', 'pin_hash', 'pin_set_at'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'pin_set_at' => 'datetime',
        ];
    }
}
