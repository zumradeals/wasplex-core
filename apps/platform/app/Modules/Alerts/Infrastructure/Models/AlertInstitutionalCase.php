<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AlertInstitutionalCase extends Model
{
    use HasUlids;

    public const STATUS_REFERRED = 'referred';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'alert_declaration_id',
        'professional_space_id',
        'organization_id',
        'referred_by_account_id',
        'assigned_account_id',
        'status',
        'referral_reason',
        'institutional_reference',
        'resolution_note',
        'referred_at',
        'acknowledged_at',
        'started_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'referral_reason' => 'encrypted',
            'resolution_note' => 'encrypted',
            'referred_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(AlertDeclaration::class, 'alert_declaration_id');
    }

    public function professionalSpace(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSpace::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'referred_by_account_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_account_id');
    }
}
