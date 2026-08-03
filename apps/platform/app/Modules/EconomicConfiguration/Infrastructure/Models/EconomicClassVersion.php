<?php

namespace App\Modules\EconomicConfiguration\Infrastructure\Models;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EconomicClassVersion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => ConfigurationState::class,
            'quota_monthly' => 'integer',
            'weight_basis_points' => 'integer',
            'targeting_coefficient_basis_points' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'features' => 'array',
        ];
    }

    public function economicClass(): BelongsTo
    {
        return $this->belongsTo(EconomicClass::class);
    }
}
