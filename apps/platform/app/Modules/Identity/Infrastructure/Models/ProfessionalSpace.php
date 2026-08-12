<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProfessionalSpace extends Model
{
    use HasUlids;

    public const KIND_PARTNER = 'partner';
    public const KIND_MERCHANT = 'merchant';
    public const KIND_SERVICE_PROVIDER = 'service_provider';
    public const KIND_SECURITY_INSTITUTION = 'security_institution';
    public const KIND_HEALTHCARE_INSTITUTION = 'healthcare_institution';
    public const KIND_HEALTH_PROFESSIONAL = 'health_professional';
    public const KIND_FINANCIAL_OPERATOR = 'financial_operator';
    public const KIND_FIELD_AGENT = 'field_agent';
    public const KIND_MODERATION_TEAM = 'moderation_team';
    public const KIND_WASPLEX_OPERATIONS = 'wasplex_operations';

    public const VERIFICATION_PENDING = 'pending_verification';
    public const VERIFICATION_UNDER_REVIEW = 'under_review';
    public const VERIFICATION_VERIFIED = 'verified';
    public const VERIFICATION_RESTRICTED = 'restricted';
    public const VERIFICATION_SUSPENDED = 'suspended';
    public const VERIFICATION_REJECTED = 'rejected';

    protected $fillable = [
        'user_space_id',
        'organization_id',
        'created_by_account_id',
        'reviewed_by_account_id',
        'space_kind',
        'verification_status',
        'legal_name',
        'trade_name',
        'registration_number',
        'country_code',
        'territory',
        'purposes',
        'restrictions',
        'review_note',
        'submitted_at',
        'reviewed_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'registration_number' => 'encrypted',
            'territory' => 'array',
            'purposes' => 'array',
            'restrictions' => 'array',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function userSpace(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reviewed_by_account_id');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(ProfessionalRoleAssignment::class);
    }

    public function servicePoints(): HasMany
    {
        return $this->hasMany(ProfessionalServicePoint::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }
}
