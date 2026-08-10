<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;

/**
 * docs/17-donnees-permissions-consentements-techniques-wasplex.md L103 :
 * Santé, Alertes, Fonds et KYC ne doivent jamais servir au ciblage
 * publicitaire. Le ciblage accepte le territoire, la classe économique et
 * les seules taxonomies volontaires publiées par SmartProfile. Les clés
 * sensibles ou inconnues restent structurellement rejetées.
 */
final class AudienceConfigurationValidator
{
    private const ALLOWED_KEYS = ['territory', 'economic_classes', 'profile_taxonomies'];

    public function __construct(
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly ProfileTargetingContract $profileTargeting,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(array $configuration): void
    {
        $unknownKeys = array_diff(array_keys($configuration), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidCampaignConfigurationException(
                'Critère de ciblage non autorisé : '.implode(', ', $unknownKeys).
                ' — seuls le territoire, les classes économiques et les critères volontaires du profil sont acceptés.'
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

        if (isset($configuration['profile_taxonomies'])) {
            if (! is_array($configuration['profile_taxonomies']) || $configuration['profile_taxonomies'] === []) {
                throw new InvalidCampaignConfigurationException('Les critères de profil ciblés doivent former une liste non vide.');
            }

            $unknown = $this->profileTargeting->validateTargetableCodes($configuration['profile_taxonomies']);
            if ($unknown !== []) {
                throw new InvalidCampaignConfigurationException('Critère(s) de profil inconnu(s) ou suspendu(s) : '.implode(', ', $unknown));
            }

            $genderCriteria = array_filter(
                $configuration['profile_taxonomies'],
                fn ($code) => is_string($code) && str_starts_with($code, 'demographic.gender.'),
            );
            if (count($genderCriteria) > 1) {
                throw new InvalidCampaignConfigurationException('Une campagne ne peut sélectionner qu’un seul genre déclaré.');
            }
        }
    }
}
