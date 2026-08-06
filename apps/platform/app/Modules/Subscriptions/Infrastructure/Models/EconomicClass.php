<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EconomicClass extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const CODE_FREE = 'FREE';

    public const CODE_PREMIUM = 'PREMIUM';

    public const CODE_GOLD = 'GOLD';

    public const CODE_PLATINUM = 'PLATINUM';

    protected $table = 'economic_classes';

    protected $fillable = ['code', 'status'];

    public function versions(): HasMany
    {
        return $this->hasMany(EconomicClassVersion::class, 'economic_class_id');
    }
}
