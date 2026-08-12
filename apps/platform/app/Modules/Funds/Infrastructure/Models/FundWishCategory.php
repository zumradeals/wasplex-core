<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class FundWishCategory extends Model
{
    use HasUlids;

    protected $table = 'fund_wish_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_sensitive_documents' => 'boolean',
        ];
    }
}
