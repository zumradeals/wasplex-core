<?php

declare(strict_types=1);

namespace App\Modules\Funds\Application\Services;

use App\Modules\Funds\Infrastructure\Models\FundOrder;
use App\Modules\Funds\Infrastructure\Models\FundOrderWarranty;
use App\Modules\Funds\Infrastructure\Models\FundWarrantyClaim;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundWarrantyService
{
    public function register(
        FundOrder $order,
        string $organizationId,
        string $actorAccountId,
        ?string $reference,
        ?string $terms,
        string $startsAt,
        string $endsAt,
    ): FundOrderWarranty {
        if ((string) $order->provider_organization_id !== $organizationId) {
            throw new RuntimeException('Commande introuvable.');
        }
        if ($order->delivered_at === null) {
            throw new RuntimeException('La garantie ne peut être enregistrée qu’après livraison.');
        }

        $start = CarbonImmutable::parse($startsAt);
        $end = CarbonImmutable::parse($endsAt);
        if ($end->lessThanOrEqualTo($start)) {
            throw new RuntimeException('La fin de garantie doit être postérieure au début.');
        }

        return DB::transaction(function () use ($order, $organizationId, $actorAccountId, $reference, $terms, $start, $end): FundOrderWarranty {
            $warranty = FundOrderWarranty::query()->where('fund_order_id', $order->id)->lockForUpdate()->first();
            if ($warranty !== null) {
                throw new RuntimeException('Une garantie est déjà enregistrée pour cette commande.');
            }

            return FundOrderWarranty::query()->create([
                'fund_order_id' => $order->id,
                'provider_organization_id' => $organizationId,
                'status' => 'active',
                'reference' => $reference,
                'terms' => $terms,
                'starts_at' => $start,
                'ends_at' => $end,
                'registered_by_account_id' => $actorAccountId,
            ]);
        });
    }

    public function openClaim(FundOrder $order, string $accountId, string $issue): FundWarrantyClaim
    {
        $order->load('wish');
        if ((string) $order->wish->account_id !== $accountId) {
            throw new RuntimeException('Commande introuvable.');
        }

        return DB::transaction(function () use ($order, $accountId, $issue): FundWarrantyClaim {
            $warranty = FundOrderWarranty::query()->where('fund_order_id', $order->id)->lockForUpdate()->first();
            if ($warranty === null) {
                throw new RuntimeException('Aucune garantie n’est enregistrée pour cette commande.');
            }
            if ($warranty->ends_at->isPast()) {
                $warranty->update(['status' => 'expired']);
                throw new RuntimeException('La garantie est expirée.');
            }
            if ($warranty->starts_at->isFuture() || ! in_array($warranty->status, ['active', 'claim_open'], true)) {
                throw new RuntimeException('La garantie n’est pas active.');
            }
            if ($warranty->claims()->whereIn('status', ['open', 'provider_responded'])->exists()) {
                throw new RuntimeException('Une réclamation de garantie est déjà ouverte.');
            }

            $claim = $warranty->claims()->create([
                'opened_by_account_id' => $accountId,
                'status' => 'open',
                'issue' => $issue,
                'opened_at' => now(),
            ]);
            $warranty->update(['status' => 'claim_open']);

            return $claim;
        });
    }

    public function respond(
        FundOrder $order,
        string $organizationId,
        string $actorAccountId,
        string $claimId,
        string $response,
    ): FundWarrantyClaim {
        if ((string) $order->provider_organization_id !== $organizationId) {
            throw new RuntimeException('Commande introuvable.');
        }

        return DB::transaction(function () use ($order, $actorAccountId, $claimId, $response): FundWarrantyClaim {
            $warranty = FundOrderWarranty::query()->where('fund_order_id', $order->id)->first();
            if ($warranty === null) {
                throw new RuntimeException('Garantie introuvable.');
            }
            $claim = FundWarrantyClaim::query()
                ->where('id', $claimId)
                ->where('fund_order_warranty_id', $warranty->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();
            if ($claim === null) {
                throw new RuntimeException('Réclamation ouverte introuvable.');
            }

            $claim->update([
                'status' => 'provider_responded',
                'provider_response' => $response,
                'responded_by_account_id' => $actorAccountId,
                'responded_at' => now(),
            ]);

            return $claim->refresh();
        });
    }

    public function resolve(
        FundOrder $order,
        string $claimId,
        string $actorAccountId,
        string $resolutionCode,
        string $resolutionNote,
    ): FundWarrantyClaim {
        return DB::transaction(function () use ($order, $claimId, $actorAccountId, $resolutionCode, $resolutionNote): FundWarrantyClaim {
            $warranty = FundOrderWarranty::query()->where('fund_order_id', $order->id)->lockForUpdate()->first();
            if ($warranty === null) {
                throw new RuntimeException('Garantie introuvable.');
            }
            $claim = FundWarrantyClaim::query()
                ->where('id', $claimId)
                ->where('fund_order_warranty_id', $warranty->id)
                ->whereIn('status', ['open', 'provider_responded'])
                ->lockForUpdate()
                ->first();
            if ($claim === null) {
                throw new RuntimeException('Réclamation active introuvable.');
            }

            $claim->update([
                'status' => 'resolved',
                'resolution_code' => $resolutionCode,
                'resolution_note' => $resolutionNote,
                'resolved_by_account_id' => $actorAccountId,
                'resolved_at' => now(),
            ]);
            $warranty->update([
                'status' => $warranty->ends_at->isPast() ? 'expired' : 'active',
            ]);

            return $claim->refresh();
        });
    }
}
