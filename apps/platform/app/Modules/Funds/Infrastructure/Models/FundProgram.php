<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FundProgram extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $table = 'fund_programs';
    protected $fillable = ['code', 'name', 'status', 'sort_order'];

    public function versions(): HasMany
    {
        return $this->hasMany(FundProgramVersion::class, 'fund_program_id');
    }

    public function publishedVersion(): ?FundProgramVersion
    {
        return $this->versions()->where('status', 'published')->latest('version')->first();
    }
}
