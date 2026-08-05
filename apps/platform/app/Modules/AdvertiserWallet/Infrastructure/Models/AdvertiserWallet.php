<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AdvertiserWallet extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'advertiser_wallets';

    protected $fillable = ['organization_id', 'currency', 'status'];
}
