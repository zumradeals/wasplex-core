<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;

/**
 * docs/17-donnees-permissions-consentements-techniques-wasplex.md L103 :
 * Santé, Alertes, Fonds et KYC ne doivent jamais servir au ciblage
 * publicitaire. Ce chantier ne construit que deux critères réels
 * (territoire déclaré du compte, classe économique) — toute autre clé est
 * structurellement rejetée, ce qui rend l'interdiction impossible à
 * contourner plutôt que simplement non implémentée (docs/chantiers/
 * P006-CHANTIER.md §3.3).
 */
final class AudienceConfigurationValidator
{
    private const ALLOWED_KEYS = ['territory', 'economic_classes'];

    public function __construct(private readonly EconomicClassCatalogContract $economicClasses) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(array $configuration): void
    {
        $unknownKeys = array_diff(array_keys($configuration), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidCampaignConfigurationException(
                'Critère de ciblage non autorisé : '.implode(', ', $unknownKeys).
                ' — seuls "territory" et "economic_classes" sont acceptés.'
            );
        }

        if (isset($configuration['territory']) && ! is_array($configuration['territory'])) {
            throw new InvalidCampaignConfigurationException('"territory" doit être un objet avec country_code.');
        }

        if (isset($configuration['economic_classes'])) {
            if (! is_array($configuration['economic_classes']) || $configuration['economic_classes'] === []) {
                throw new InvalidCampaignConfigurationException('Au moins une classe économique doit être ciblée.');
            }

            $validCodes = array_map(fn ($summary) => $summary->code, $this->economicClasses->listActive());
            $unknownClasses = array_diff($configuration['economic_classes'], $validCodes);

            if ($unknownClasses !== []) {
                throw new InvalidCampaignConfigurationException(
                    'Classe(s) économique(s) inconnue(s) : '.implode(', ', $unknownClasses)
                );
            }
        }
    }
}
