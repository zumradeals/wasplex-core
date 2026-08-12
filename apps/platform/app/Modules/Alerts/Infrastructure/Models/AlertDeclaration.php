<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AlertDeclaration extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'category',
        'situation',
        'priority',
        'status',
        'title',
        'description',
        'public_summary',
        'city',
        'area_label',
        'exact_location',
        'public_visibility',
        'submitted_at',
        'published_at',
        'resolved_at',
        'expires_at',
    ];

    protected $hidden = ['exact_location'];

    protected function casts(): array
    {
        return [
            'exact_location' => 'encrypted',
            'public_visibility' => 'boolean',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'resolved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->public_visibility
            && $this->status === 'published'
            && $this->published_at !== null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->resolved_at === null;
    }
}
