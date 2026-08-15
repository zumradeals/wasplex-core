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
        $this->assertOwnedActiveCard($card, $accountId);

        return DB::transaction(function () use ($card): array {
            CardQrToken::query()
                ->where('card_id', $card->id)
                ->where('purpose', CardQrToken::PURPOSE_PUBLIC_IDENTITY)
                ->where('status', CardQrToken::STATUS_ACTIVE)
                ->update(['status' => CardQrToken::STATUS_REVOKED]);

            $expiresAt = now()->addMinutes(2);
            $secret = (string) Str::uuid();
            $token = CardQrToken::query()->create([
                'card_id' => $card->id,
                'purpose' => CardQrToken::PURPOSE_PUBLIC_IDENTITY,
                'token_hash' => hash('sha256', $secret),
                'status' => CardQrToken::STATUS_ACTIVE,
                'currency' => 'WP',
                'expires_at' => $expiresAt,
            ]);

            $payload = url('/api/cards/qr/check').'?token='.rawurlencode($secret);
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

    public function generateReceiveQr(Card $card, string $accountId, ?int $amountMinor, ?string $note): array
    {
        $this->assertOwnedActiveCard($card, $accountId);

        return DB::transaction(function () use ($card, $amountMinor, $note): array {
            CardQrToken::query()
                ->where('card_id', $card->id)
                ->where('purpose', CardQrToken::PURPOSE_RECEIVE_PAYMENT)
                ->where('status', CardQrToken::STATUS_ACTIVE)
                ->update(['status' => CardQrToken::STATUS_REVOKED]);

            $expiresAt = now()->addMinutes(5);
            $secret = (string) Str::uuid();
            $token = CardQrToken::query()->create([
                'card_id' => $card->id,
                'purpose' => CardQrToken::PURPOSE_RECEIVE_PAYMENT,
                'token_hash' => hash('sha256', $secret),
                'status' => CardQrToken::STATUS_ACTIVE,
                'amount_minor' => $amountMinor,
                'currency' => 'WP',
                'note' => $note === null || trim($note) === '' ? null : trim($note),
                'expires_at' => $expiresAt,
            ]);

            $payload = 'WPLX:RECEIVE:'.$secret;
            $this->audit->record($card, 'CardReceiveQrGenerated', [
                'token_id' => $token->id,
                'amount_minor' => $amountMinor,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return [
                'token_id' => $token->id,
                'purpose' => $token->purpose,
                'payload' => $payload,
                'amount_minor' => $token->amount_minor,
                'currency' => $token->currency,
                'note' => $token->note,
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
                ->where('purpose', CardQrToken::PURPOSE_PUBLIC_IDENTITY)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new NotFoundHttpException('QR Carte Wasplex introuvable.');
            }

            $this->assertUsable($locked);
            $card = $locked->card;

            $locked->update(['status' => CardQrToken::STATUS_USED, 'used_at' => now()]);
            $this->audit->record($card, 'CardQrResolved', ['token_id' => $locked->id]);

            return [
                'valid' => true,
                'card' => $this->minimalCard($card),
            ];
        });
    }

    public function previewReceive(string $payload, string $scannerAccountId): array
    {
        $secret = $this->extractReceiveSecret($payload);
        $token = CardQrToken::query()
            ->with('card.account.profile', 'card.offerVersion.offer')
            ->where('token_hash', hash('sha256', $secret))
            ->where('purpose', CardQrToken::PURPOSE_RECEIVE_PAYMENT)
            ->first();

        if ($token === null) {
            throw new NotFoundHttpException('QR de réception Wasplex introuvable.');
        }

        $this->assertUsable($token);
        if ($token->card->account_id === $scannerAccountId) {
            throw new ConflictHttpException('Vous ne pouvez pas payer votre propre Carte Wasplex.');
        }

        return [
            'action' => 'card_payment',
            'recipient' => $this->minimalCard($token->card),
            'amount_minor' => $token->amount_minor,
            'currency' => $token->currency,
            'note' => $token->note,
            'expires_at' => $token->expires_at->toIso8601String(),
            'amount_locked' => $token->amount_minor !== null,
        ];
    }

    public function lockReceiveForPayment(string $payload): CardQrToken
    {
        $secret = $this->extractReceiveSecret($payload);
        $token = CardQrToken::query()
            ->with('card.account.profile', 'card.offerVersion.offer')
            ->where('token_hash', hash('sha256', $secret))
            ->where('purpose', CardQrToken::PURPOSE_RECEIVE_PAYMENT)
            ->lockForUpdate()
            ->first();

        if ($token === null) {
            throw new NotFoundHttpException('QR de réception Wasplex introuvable.');
        }

        $this->assertUsable($token);

        return $token;
    }

    public function tokenHash(string $payload): string
    {
        return hash('sha256', $this->extractReceiveSecret($payload));
    }

    public function consumeReceive(CardQrToken $token): void
    {
        $token->update([
            'status' => CardQrToken::STATUS_USED,
            'used_at' => now(),
        ]);
    }

    private function assertOwnedActiveCard(Card $card, string $accountId): void
    {
        if ($card->account_id !== $accountId) {
            throw new NotFoundHttpException('Carte Wasplex introuvable.');
        }
        if ($card->status !== Card::STATUS_ACTIVE) {
            throw new ConflictHttpException('La Carte Wasplex doit être active pour générer un QR.');
        }
    }

    private function assertUsable(CardQrToken $token): void
    {
        if ($token->status !== CardQrToken::STATUS_ACTIVE || $token->used_at !== null) {
            throw new GoneHttpException('Ce QR Carte Wasplex a déjà été utilisé ou révoqué.');
        }
        if ($token->expires_at->isPast()) {
            $token->update(['status' => CardQrToken::STATUS_REVOKED]);
            throw new GoneHttpException('Ce QR Carte Wasplex a expiré.');
        }
        if ($token->card->status !== Card::STATUS_ACTIVE) {
            throw new GoneHttpException('Cette Carte Wasplex n’est pas active.');
        }
    }

    private function extractReceiveSecret(string $payload): string
    {
        $payload = trim($payload);
        if (str_starts_with($payload, 'WPLX:RECEIVE:')) {
            $secret = substr($payload, strlen('WPLX:RECEIVE:'));
        } else {
            $secret = $payload;
        }

        if ($secret === '' || strlen($secret) > 80) {
            throw new NotFoundHttpException('QR de réception Wasplex invalide.');
        }

        return $secret;
    }

    private function minimalCard(Card $card): array
    {
        return [
            'public_identifier' => $card->public_identifier,
            'status' => $card->status,
            'display_name' => $card->account->profile?->display_name ?: 'Membre Wasplex',
            'offer_name' => $card->offerVersion->offer->name,
            'verified' => $card->account->verified_at !== null,
        ];
    }
}
