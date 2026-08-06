<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConsentPurposeVersion extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $table = 'consent_purpose_versions';

    protected $fillable = [
        'consent_purpose_id', 'version_number', 'name', 'description',
        'requires_new_decision', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_new_decision' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'consent_purpose_id');
    }
}
