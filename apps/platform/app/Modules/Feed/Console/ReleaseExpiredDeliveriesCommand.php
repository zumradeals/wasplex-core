<?php

declare(strict_types=1);

namespace App\Modules\Feed\Console;

use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use Illuminate\Console\Command;

/**
 * docs/07 §48 : « relâcher les réservations expirées ». Aucun worker
 * planifié dans ce chantier (docs/chantiers/P009-CHANTIER.md §2.8) — à
 * appeler manuellement ou via une tâche planifiée future.
 */
final class ReleaseExpiredDeliveriesCommand extends Command
{
    protected $signature = 'feed:release-expired-deliveries';

    protected $description = 'Libère les enveloppes de campagne des livraisons réservées jamais démarrées ni complétées avant expiration.';

    public function handle(CampaignEnvelopeContract $envelope): int
    {
        $ttlSeconds = (int) config('campaigns.envelope_reservation_ttl_seconds');
        $cutoff = now()->subSeconds($ttlSeconds);

        $expired = FeedAdDelivery::query()
            ->whereIn('status', [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED])
            ->where('reserved_at', '<', $cutoff)
            ->get();

        foreach ($expired as $delivery) {
            $envelope->releaseSlot($delivery->campaign_envelope_consumption_id);
            $delivery->update(['status' => FeedAdDelivery::STATUS_EXPIRED]);
        }

        $this->info("{$expired->count()} livraison(s) expirée(s) libérée(s).");

        return self::SUCCESS;
    }
}
