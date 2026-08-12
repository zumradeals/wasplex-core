<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdentityVerification extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'status',
        'document_type',
        'document_country',
        'document_number',
        'birth_date',
        'document_front_path',
        'selfie_path',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
    ];

    protected $hidden = [
        'document_number',
        'birth_date',
        'document_front_path',
        'selfie_path',
    ];

    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
            'birth_date' => 'encrypted',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
