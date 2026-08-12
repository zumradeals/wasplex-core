<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HealthAccessEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'patient_id',
        'actor_account_id',
        'professional_space_id',
        'organization_id',
        'actor_type',
        'access_type',
        'purpose',
        'consent_basis',
        'institution_name',
        'justification',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(HealthPatient::class, 'patient_id');
    }

    public function professionalSpace(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSpace::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
