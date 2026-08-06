<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BrandGuideline extends Model
{
    use HasUlids;

    protected $fillable = [
        'brand_id', 'tone', 'custom_instructions', 'mandatory_mentions',
        'forbidden_mentions', 'usage_rules',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
