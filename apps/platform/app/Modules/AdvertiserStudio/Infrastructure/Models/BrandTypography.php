<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BrandTypography extends Model
{
    use HasUlids;

    protected $fillable = ['brand_id', 'role', 'family', 'usages', 'recommended_sizes'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
