<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HealthProfessionalAccessRequest extends Model
{
    use HasUlids;

    public const PURPOSE_CARE_CAPSULE = 'care_capsule';

    public const PURPOSE_EMERGENCY_CAPSULE = 'emergency_capsule';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'patient_id',
        'professional_space_id',
        'organization_id',
        'requester_account_id',
        'decided_by_account_id',
        'purpose',
        'status',
        'justification',
        'requested_at',
        'decided_at',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'justification' => 'encrypted',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'requester_account_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'decided_by_account_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
