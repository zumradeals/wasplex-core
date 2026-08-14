<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CardSuspensionService
{
    public function __construct(private readonly CardAudit $audit) {}

    public function suspend(Card $card, string $accountId): Card
    {
        if ($card->account_id !== $accountId) {
            throw new NotFoundHttpException('Carte Wasplex introuvable.');
        }

        return DB::transaction(function () use ($card): Card {
            $locked = Card::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Card::STATUS_SUSPENDED) {
                return $locked->load('offerVersion.offer');
            }
            if ($locked->status !== Card::STATUS_ACTIVE) {
                throw new ConflictHttpException('Cette Carte Wasplex ne peut pas être suspendue dans son état actuel.');
            }

            $locked->update(['status' => Card::STATUS_SUSPENDED, 'suspended_at' => now()]);
            CardQrToken::query()
                ->where('card_id', $locked->id)
                ->where('status', CardQrToken::STATUS_ACTIVE)
                ->update(['status' => CardQrToken::STATUS_REVOKED]);

            $this->audit->record($locked, 'CardSuspended');

            return $locked->refresh()->load('offerVersion.offer');
        });
    }
}
