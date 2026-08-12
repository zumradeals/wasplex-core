<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProfessionalRoleAssignment extends Model
{
    use HasUlids;

    protected $fillable = [
        'professional_space_id',
        'account_id',
        'role_code',
        'status',
        'scope',
        'starts_at',
        'ends_at',
        'assigned_by_account_id',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function professionalSpace(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSpace::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_by_account_id');
    }
}
