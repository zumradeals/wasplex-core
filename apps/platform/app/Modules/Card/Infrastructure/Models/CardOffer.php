<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CardOffer extends Model
{
    use HasUlids;

    protected $fillable = ['code', 'name', 'description', 'status', 'sort_order'];

    public function versions(): HasMany
    {
        return $this->hasMany(CardOfferVersion::class, 'offer_id');
    }
}
