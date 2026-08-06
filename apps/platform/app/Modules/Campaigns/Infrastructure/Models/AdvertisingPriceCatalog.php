<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AdvertisingPriceCatalog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['code'];

    public function versions(): HasMany
    {
        return $this->hasMany(AdvertisingPriceVersion::class, 'catalog_id');
    }
}
