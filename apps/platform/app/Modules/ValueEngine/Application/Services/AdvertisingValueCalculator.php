<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\Campaign\Infrastructure\Models\CampaignQuote;
use App\Modules\ValueEngine\Application\Data\AdvertisingValueQuote;
use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\ValueEngine\Infrastructure\Models\ValueRuleVersion;
use JsonException;

final class AdvertisingValueCalculator
{
    public function quote(CampaignQuote $campaignQuote, ValueRuleVersion $rule): AdvertisingValueQuote
    {
        if ($campaignQuote->estimated_impressions <= 0 || $campaignQuote->gross_amount_minor <= 0) {
            throw new ValueEngineException('Le devis de campagne ne permet pas de calculer un événement rémunéré.');
        }

        if ($rule->user_share_basis_points + $rule->wasplex_share_basis_points !== 10000) {
            throw new ValueEngineException('La règle de valeur ne répartit pas exactement 100 % de la valeur.');
        }

        $gross = intdiv($campaignQuote->gross_amount_minor, $campaignQuote->estimated_impressions);
        $gross = max($rule->minimum_event_amount_minor, $gross);

        if ($rule->maximum_event_amount_minor !== null) {
            $gross = min($rule->maximum_event_amount_minor, $gross);
        }

        if ($gross <= 0) {
            throw new ValueEngineException('Le montant unitaire calculé doit être strictement positif.');
        }

        $user = intdiv($gross * $rule->user_share_basis_points, 10000);
        $wasplex = $gross - $user;

        if ($user <= 0 || $wasplex <= 0) {
            throw new ValueEngineException('La règle doit produire une part utilisateur et une part Wasplex positives.');
        }

        $payload = [
            'campaign_quote_id' => $campaignQuote->id,
            'campaign_quote_version' => $campaignQuote->version,
            'gross_amount_minor' => $gross,
            'user_amount_minor' => $user,
            'wasplex_amount_minor' => $wasplex,
            'unit' => $campaignQuote->unit,
            'currency' => $campaignQuote->currency,
            'rule_id' => $rule->id,
            'rule_code' => $rule->code,
            'rule_version' => $rule->version,
        ];

        return new AdvertisingValueQuote(
            grossAmountMinor: $gross,
            userAmountMinor: $user,
            wasplexAmountMinor: $wasplex,
            unit: $campaignQuote->unit,
            currency: $campaignQuote->currency,
            ruleVersionId: $rule->id,
            calculationHash: hash('sha256', $this->encode($payload)),
        );
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        ksort($payload);

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ValueEngineException('Le devis de valeur ne peut pas être canonisé.', 0, $exception);
        }
    }
}
