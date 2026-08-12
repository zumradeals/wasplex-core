<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

final class CreativeAsset extends Model
{
    use HasUlids;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_LOGO = 'logo';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_READY = 'ready';

    public const STATUS_NEEDS_CHANGES = 'needs_changes';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'brand_id', 'type', 'filename', 'format', 'size', 'width', 'height', 'duration', 'duration_ms',
        'language', 'rights_status', 'moderation_status', 'storage_disk', 'storage_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function moderationCases(): HasMany
    {
        return $this->hasMany(CreativeModerationCase::class);
    }

    public function url(): string
    {
        return Storage::disk($this->storage_disk)->url($this->storage_path);
    }
}
