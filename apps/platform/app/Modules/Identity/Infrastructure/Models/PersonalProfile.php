<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PersonalProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'display_name',
        'photo_path',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $displayName = trim((string) $value);
                if ($displayName !== '') {
                    return $displayName;
                }

                $fullName = trim(implode(' ', array_filter([
                    trim((string) ($attributes['first_name'] ?? '')),
                    trim((string) ($attributes['last_name'] ?? '')),
                ])));

                return $fullName !== '' ? $fullName : null;
            },
            set: fn (mixed $value): mixed => $value,
        );
    }

    public function resolvedDisplayName(): string
    {
        return $this->display_name ?: 'Membre Wasplex';
    }
}
