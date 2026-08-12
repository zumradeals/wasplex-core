<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProfessionalServicePoint extends Model
{
    use HasUlids;

    protected $fillable = [
        'professional_space_id',
        'name',
        'type',
        'country_code',
        'city',
        'area_label',
        'address',
        'coordinates',
        'opening_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'opening_hours' => 'array',
        ];
    }

    public function professionalSpace(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSpace::class);
    }
}
