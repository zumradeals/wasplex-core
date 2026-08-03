<?php

namespace App\Modules\EconomicConfiguration\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EconomicClass extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function versions(): HasMany
    {
        return $this->hasMany(EconomicClassVersion::class);
    }
}
