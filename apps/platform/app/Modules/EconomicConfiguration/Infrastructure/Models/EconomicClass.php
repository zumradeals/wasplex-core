<?php

namespace App\Modules\EconomicConfiguration\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $status
 */
final class EconomicClass extends Model
{
    use HasUuids;

    protected $guarded = [];

    /** @return HasMany<EconomicClassVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(EconomicClassVersion::class);
    }
}
