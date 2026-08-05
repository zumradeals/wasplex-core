<?php

namespace App\Modules\Distribution\Application\Services;

use App\Modules\Distribution\Domain\Enums\AdvertisingDeliveryStatus;
use App\Modules\Distribution\Domain\Exceptions\DistributionException;
use App\Modules\Distribution\Infrastructure\Models\AdvertisingDelivery;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class FeedInteractionService
{
    public function __construct(
        private AdvertisingDeliveryOutbox $outbox,
    ) {}

    public function dismiss(
        Account $account,
        AdvertisingDelivery $delivery,
        string $idempotencyKey,
        string $reason,
    ): AdvertisingDelivery {
        return DB::transaction(function () use ($account, $delivery, $idempotencyKey, $reason): AdvertisingDelivery {
            $locked = AdvertisingDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->account_id !== $account->id) {
                throw new DistributionException('Cette livraison ne correspond pas au compte authentifié.');
            }

            if ($locked->status !== AdvertisingDeliveryStatus::Delivered) {
                throw new DistributionException('Seule une publicité déjà livrée peut être masquée.');
            }

            $eventKey = 'feed-item-dismissed:'.$locked->id;
            DB::table('advertising_delivery_events')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'advertising_delivery_id' => $locked->id,
                'event_type' => 'FeedItemDismissed',
                'idempotency_key' => $eventKey,
                'occurred_at' => now(),
                'metadata' => json_encode([
                    'reason' => $reason,
                    'request_idempotency_hash' => hash('sha256', $idempotencyKey),
                    'quota_restored' => false,
                    'financial_operation_created' => false,
                    'wallet_operation_created' => false,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            $this->outbox->record(
                'advertising_delivery',
                $locked->id,
                'FEED_ITEM_DISMISSED',
                $eventKey,
                [
                    'delivery_id' => $locked->id,
                    'feed_session_id' => $locked->feed_session_id,
                    'campaign_id' => $locked->campaign_id,
                    'reason' => $reason,
                    'quota_restored' => false,
                    'financial_operation_created' => false,
                    'wallet_operation_created' => false,
                ],
            );

            DB::afterCommit(static fn () => event('distribution.feed-item-dismissed', [$locked->id]));

            return $locked->refresh();
        }, 5);
    }
}
