<?php

declare(strict_types=1);

namespace App\Modules\Feed\Console;

use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * docs/07 §40 : « workers de reprise » (réservations expirées, livraisons
 * bloquées, transactions non publiées). Aucun worker planifié dans ce
 * chantier (docs/chantiers/P009-CHANTIER.md §2.8, confirmé par
 * docs/chantiers/P010-CHANTIER.md §2.8/§6) — à appeler manuellement ou via
 * une tâche planifiée future.
 */
final class ReleaseExpiredDeliveriesCommand extends Command
{
    protected $signature = 'feed:release-expired-deliveries';

    protected $description = 'Libère les réservations et livraisons Feed bloquées, et signale toute livraison complétée sans transaction Grand Livre associée.';

    public function handle(CampaignEnvelopeContract $envelope): int
    {
        $reservationTtlSeconds = (int) config('campaigns.envelope_reservation_ttl_seconds');
        $reservationCutoff = now()->subSeconds($reservationTtlSeconds);

        $expiredReservations = FeedAdDelivery::query()
            ->where('status', FeedAdDelivery::STATUS_RESERVED)
            ->where('reserved_at', '<', $reservationCutoff)
            ->get();

        foreach ($expiredReservations as $delivery) {
            $envelope->releaseSlot($delivery->campaign_envelope_consumption_id);
            $delivery->update(['status' => FeedAdDelivery::STATUS_EXPIRED]);
        }

        // Une livraison "started" reste légitimement active plus longtemps
        // qu'une simple réservation (elle joue réellement le média) — le
        // délai est donc proportionnel à sa propre durée requise, pas à la
        // TTL fixe de réservation (docs/chantiers/P010-CHANTIER.md §6).
        $stuckMultiplier = (int) config('feed.stuck_delivery_timeout_multiplier');
        $stuckCount = 0;

        foreach (FeedAdDelivery::query()->where('status', FeedAdDelivery::STATUS_STARTED)->get() as $delivery) {
            $lastActivityAt = $delivery->last_heartbeat_at ?? $delivery->started_at;
            $timeoutSeconds = (int) ($delivery->required_duration_ms * $stuckMultiplier / 1000);

            if ($lastActivityAt === null || $lastActivityAt->addSeconds($timeoutSeconds)->isFuture()) {
                continue;
            }

            $envelope->releaseSlot($delivery->campaign_envelope_consumption_id);
            $delivery->update(['status' => FeedAdDelivery::STATUS_EXPIRED]);
            $stuckCount++;
        }

        // Filet de sécurité, pas un correctif : impossible par construction
        // (transaction atomique, docs/chantiers/P009-CHANTIER.md §2.4/§20)
        // mais activement recherché et journalisé plutôt que corrigé en
        // silence (docs/CLAUDE.md §16).
        $orphaned = FeedAdDelivery::query()
            ->where('status', FeedAdDelivery::STATUS_COMPLETED)
            ->whereNull('ledger_transaction_id')
            ->get();

        foreach ($orphaned as $delivery) {
            Log::critical('feed.delivery.completed_without_ledger_transaction', ['delivery_id' => $delivery->id]);
        }

        $this->info("{$expiredReservations->count()} réservation(s) expirée(s) libérée(s), {$stuckCount} livraison(s) bloquée(s) libérée(s), {$orphaned->count()} anomalie(s) signalée(s).");

        return self::SUCCESS;
    }
}
