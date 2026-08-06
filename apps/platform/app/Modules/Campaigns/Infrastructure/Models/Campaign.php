<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Campaign extends Model
{
    use HasUlids;

    public const TYPE_FAST = 'fast';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_FUNDED = 'funded';

    public const STATUS_SUBMITTED = 'submitted';

    public const OBJECTIVES = [
        'faire_connaitre' => 'En savoir plus',
        'obtenir_appels' => 'Appeler',
        'recevoir_messages' => 'Envoyer un message',
        'visiter_site' => 'Visiter le site',
        'promouvoir_produit' => 'Découvrir',
        'promouvoir_evenement' => 'Voir l\'événement',
        'obtenir_inscriptions' => 'S\'inscrire',
        'inviter_live' => 'Rejoindre le Live',
    ];

    protected $fillable = [
        'organization_id', 'brand_id', 'type', 'objective_code', 'status', 'currency',
        'budget_amount_minor', 'scheduled_start', 'scheduled_end', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount_minor' => 'integer',
            'scheduled_start' => 'immutable_date',
            'scheduled_end' => 'immutable_date',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CampaignVersion::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(CampaignBudgetReservation::class);
    }

    public function currentVersion(): ?CampaignVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    public static function ctaFor(?string $objectiveCode): ?string
    {
        return self::OBJECTIVES[$objectiveCode] ?? null;
    }
}
