<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HealthRepresentative extends Model
{
    use HasUlids;

    protected $fillable = [
        'patient_id',
        'representative_name',
        'representative_phone',
        'relationship',
        'scope',
        'status',
        'proof_status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'representative_name' => 'encrypted',
            'representative_phone' => 'encrypted',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(HealthPatient::class, 'patient_id');
    }
}
