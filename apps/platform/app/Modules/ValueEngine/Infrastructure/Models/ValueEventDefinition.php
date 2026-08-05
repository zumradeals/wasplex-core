<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $source_module
 * @property string $status
 * @property bool $requires_reservation
 * @property string|null $proof_type
 * @property int $schema_version
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, ValueRuleVersion> $rules
 */
final class ValueEventDefinition extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'source_module',
        'status',
        'requires_reservation',
        'proof_type',
        'schema_version',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requires_reservation' => 'boolean',
            'schema_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<ValueRuleVersion, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(ValueRuleVersion::class);
    }
}
