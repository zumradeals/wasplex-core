<?php

namespace App\Modules\EconomicConfiguration\Infrastructure\Models;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $economic_class_id
 * @property int $version
 * @property ConfigurationState $state
 * @property string $public_name
 * @property int $quota_monthly
 * @property int $weight_basis_points
 * @property int $targeting_coefficient_basis_points
 * @property array<string, mixed>|null $features
 */
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

    /** @return BelongsTo<EconomicClass, $this> */
    public function economicClass(): BelongsTo
    {
        return $this->belongsTo(EconomicClass::class);
    }
}
