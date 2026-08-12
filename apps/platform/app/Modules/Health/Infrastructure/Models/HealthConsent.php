<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HealthConsent extends Model
{
    use HasUlids;

    protected $fillable = [
        'patient_id',
        'purpose',
        'granted',
        'version',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(HealthPatient::class, 'patient_id');
    }
}
