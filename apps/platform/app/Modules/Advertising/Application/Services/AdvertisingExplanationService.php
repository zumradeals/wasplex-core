<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Identity\Infrastructure\Models\Account;

final class AdvertisingExplanationService
{
    /** @return array<string, mixed> */
    public function explain(Account $account, AdvertisingMatch $match): array
    {
        abort_unless($match->account_id === $account->id, 404);
        $reasons = [];

        foreach ($match->explanation_tokens as $token) {
            if (! is_string($token)) {
                continue;
            }

            $reason = $this->reason($token);

            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        return [
            'matchId' => $match->id,
            'campaignId' => $match->campaign_id,
            'decision' => $match->decision,
            'scoreBand' => $match->score_band,
            'title' => $match->decision === 'eligible'
                ? 'Pourquoi cette publicité peut vous être proposée ?'
                : 'Pourquoi cette publicité n’a pas été retenue ?',
            'summary' => $match->decision === 'eligible'
                ? 'Wasplex a comparé des critères autorisés de la campagne avec vos choix volontaires, sans transmettre votre identité à l’annonceur.'
                : 'La campagne ne respecte pas actuellement toutes les conditions nécessaires pour vous être proposée.',
            'reasons' => array_values(array_unique($reasons)),
            'evaluatedAt' => $match->evaluated_at->toIso8601String(),
            'actions' => [
                [
                    'label' => 'Corriger mon profil intelligent',
                    'href' => '/mon-espace/profil-intelligent',
                ],
                [
                    'label' => 'Gérer mes consentements',
                    'href' => '/mon-espace/profil-intelligent#consentements',
                ],
            ],
            'advertiserReceivedIdentity' => false,
            'financialOperationCreated' => false,
        ];
    }

    private function reason(string $token): ?string
    {
        $parts = explode(':', $token, 3);
        $kind = $parts[0] ?? '';
        $code = $parts[1] ?? '';
        $value = $parts[2] ?? '';

        return match ($kind) {
            'class' => 'Votre classe économique autorisée correspond aux classes sélectionnées par la campagne.',
            'territory' => 'Votre zone approximative volontaire correspond au territoire de la campagne.',
            'usage' => 'Un usage que vous avez déclaré correspond à un critère autorisé de la campagne.',
            'interest' => 'Un intérêt que vous avez déclaré correspond au thème de la campagne.',
            'project' => 'Un projet volontairement renseigné correspond à l’offre présentée.',
            'declared' => $code !== '' && $value !== ''
                ? 'Une information facultative de votre profil intelligent correspond à la campagne.'
                : null,
            'consent' => 'Vous avez autorisé la finalité publicitaire nécessaire à cette sélection.',
            default => null,
        };
    }
}
