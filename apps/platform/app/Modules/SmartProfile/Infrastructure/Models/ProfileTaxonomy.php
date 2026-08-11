<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProfileTaxonomy extends Model
{
    use HasUlids;

    public const CATEGORY_DEMOGRAPHIC = 'demographic';

    public const CATEGORY_POSSESSION = 'possession';

    public const CATEGORY_USAGE = 'usage';

    public const CATEGORY_INTEREST = 'interest';

    public const CATEGORY_PROJECT = 'project';

    public const CATEGORY_SITUATION = 'situation';

    public const CATEGORY_TERRITORY = 'territory';

    public const CATEGORIES = [
        self::CATEGORY_DEMOGRAPHIC,
        self::CATEGORY_POSSESSION,
        self::CATEGORY_USAGE,
        self::CATEGORY_INTEREST,
        self::CATEGORY_PROJECT,
        self::CATEGORY_SITUATION,
        self::CATEGORY_TERRITORY,
    ];

    public const INPUT_BOOLEAN = 'boolean';

    public const INPUT_SINGLE_CHOICE = 'single_choice';

    public const INPUT_MULTI_CHOICE = 'multi_choice';

    public const INPUT_TYPES = [
        self::INPUT_BOOLEAN,
        self::INPUT_SINGLE_CHOICE,
        self::INPUT_MULTI_CHOICE,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'profile_taxonomies';

    protected $fillable = [
        'code',
        'category',
        'facet',
        'input_type',
        'label',
        'status',
        'freshness_days',
    ];

    protected function casts(): array
    {
        return ['freshness_days' => 'integer'];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProfileAnswer::class);
    }
}
