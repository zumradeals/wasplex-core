<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;

/**
 * Advertising targeting only accepts understandable audience criteria.
 * Subscription/economic classes are an internal Wasplex concern and must
 * never be selected by an advertiser.
 */
final class AudienceConfigurationValidator
{
    private const ALLOWED_KEYS = ['territory', 'profile_taxonomies'];

    public function __construct(private readonly ProfileTargetingContract $profileTargeting) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(array $configuration): void
    {
        if (array_key_exists('economic_classes', $configuration)) {
            throw new InvalidCampaignConfigurationException(
                'Les classes d’abonnement sont gérées automatiquement par Wasplex et ne peuvent pas servir de critère de ciblage annonceur.'
            );
        }

        $unknownKeys = array_diff(array_keys($configuration), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidCampaignConfigurationException(
                'Critère de ciblage non autorisé : '.implode(', ', $unknownKeys).
                ' — seuls le territoire et les critères volontaires du profil sont acceptés.'
            );
        }

        if (isset($configuration['territory']) && ! is_array($configuration['territory'])) {
            throw new InvalidCampaignConfigurationException('"territory" doit être un objet avec country_code.');
        }

        if (isset($configuration['profile_taxonomies'])) {
            if (! is_array($configuration['profile_taxonomies']) || $configuration['profile_taxonomies'] === []) {
                throw new InvalidCampaignConfigurationException('Les critères de profil ciblés doivent former une liste non vide.');
            }

            $unknown = $this->profileTargeting->validateTargetableCodes($configuration['profile_taxonomies']);
            if ($unknown !== []) {
                throw new InvalidCampaignConfigurationException('Critère(s) de profil inconnu(s) ou suspendu(s) : '.implode(', ', $unknown));
            }
        }
    }
}
