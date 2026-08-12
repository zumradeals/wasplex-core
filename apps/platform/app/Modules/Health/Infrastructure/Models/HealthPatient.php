<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class HealthPatient extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'status',
        'territory',
        'origin',
        'verification_level',
    ];

    public function capsule(): HasOne
    {
        return $this->hasOne(HealthEmergencyCapsule::class, 'patient_id');
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(HealthRepresentative::class, 'patient_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(HealthConsent::class, 'patient_id');
    }

    public function accessEvents(): HasMany
    {
        return $this->hasMany(HealthAccessEvent::class, 'patient_id');
    }

    public function professionalAccessRequests(): HasMany
    {
        return $this->hasMany(HealthProfessionalAccessRequest::class, 'patient_id');
    }
}
