<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CardQrService
{
    public function __construct(private readonly CardAudit $audit) {}

    public function generateIdentityQr(Card $card, string $accountId): array
    {
        if ($card->account_id !== $accountId) {
            throw new NotFoundHttpException('Carte Wasplex introuvable.');
        }
        if ($card->status !== Card::STATUS_ACTIVE) {
            throw new ConflictHttpException('La Carte Wasplex doit être active pour générer un QR.');
        }

        return DB::transaction(function () use ($card): array {
            CardQrToken::query()
                ->where('card_id', $card->id)
                ->where('purpose', 'public_identity')
                ->where('status', CardQrToken::STATUS_ACTIVE)
                ->update(['status' => CardQrToken::STATUS_REVOKED]);

            $expiresAt = now()->addMinutes(2);
            $secret = (string) Str::uuid();
            $token = CardQrToken::query()->create([
                'card_id' => $card->id,
                'purpose' => 'public_identity',
                'token_hash' => hash('sha256', $secret),
                'status' => CardQrToken::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
            ]);

            $payload = url('/api/cards/identity/resolve').'?token='.rawurlencode($secret);
            $this->audit->record($card, 'CardQrGenerated', [
                'token_id' => $token->id,
                'purpose' => $token->purpose,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return [
                'token_id' => $token->id,
                'purpose' => $token->purpose,
                'payload' => $payload,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });
    }

    public function resolve(string $secret): array
    {
        return DB::transaction(function () use ($secret): array {
            $locked = CardQrToken::query()
                ->with('card.account.profile', 'card.offerVersion.offer')
                ->where('token_hash', hash('sha256', $secret))
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new NotFoundHttpException('QR Carte Wasplex introuvable.');
            }
            if ($locked->status !== CardQrToken::STATUS_ACTIVE || $locked->used_at !== null) {
                throw new GoneHttpException('Ce QR Carte Wasplex a déjà été utilisé.');
            }
            if ($locked->expires_at->isPast()) {
                $locked->update(['status' => CardQrToken::STATUS_REVOKED]);
                throw new GoneHttpException('Ce QR Carte Wasplex a expiré.');
            }

            $card = $locked->card;
            if ($card->status !== Card::STATUS_ACTIVE) {
                throw new GoneHttpException('Cette Carte Wasplex n’est pas active.');
            }

            $locked->update(['status' => CardQrToken::STATUS_USED, 'used_at' => now()]);
            $this->audit->record($card, 'CardQrResolved', ['token_id' => $locked->id]);

            return [
                'valid' => true,
                'card' => [
                    'public_identifier' => $card->public_identifier,
                    'status' => $card->status,
                    'display_name' => $card->account->profile?->display_name ?: 'Membre Wasplex',
                    'offer_name' => $card->offerVersion->offer->name,
                    'verified' => $card->account->verified_at !== null,
                ],
            ];
        });
    }
}
