<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
use App\Modules\Live\Infrastructure\Models\LiveRewardHold;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use App\Shared\Http\AppException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class LiveRewardHoldService
{
    public function __construct(
        private readonly AdvertiserLiveBudgetReservationContract $budgets,
        private readonly UserWalletContract $userWallet,
        private readonly LiveRewardRiskService $risk,
        private readonly LiveRewardSettlementService $settlement,
    ) {}

    /** @param string[] $riskCodes @param array<string, mixed> $evidence */
    public function open(LiveRewardAttentionBlock $block, array $riskCodes, array $evidence = []): LiveRewardHold
    {
        return LiveRewardHold::query()->firstOrCreate(
            ['live_reward_attention_block_id' => $block->id],
            [
                'live_reward_campaign_id' => $block->live_reward_campaign_id,
                'live_id' => $block->live_id,
                'account_id' => $block->account_id,
                'block_index' => $block->block_index,
                'reward_minor' => $block->reward_minor,
                'gross_amount_minor' => $block->gross_amount_minor,
                'status' => LiveRewardHold::STATUS_PENDING,
                'risk_codes' => array_values(array_unique($riskCodes)),
                'evidence' => $evidence === [] ? null : $evidence,
                'opened_at' => now(),
            ],
        );
    }

    /** @return Collection<int, LiveRewardHold> */
    public function listQueue(): Collection
    {
        return LiveRewardHold::query()
            ->where('status', LiveRewardHold::STATUS_PENDING)
            ->with(['block', 'live'])
            ->orderBy('opened_at')
            ->get();
    }

    public function release(string $holdId, string $adminAccountId, ?string $note = null): LiveRewardHold
    {
        $rewarded = false;
        $accountId = null;
        $amountMinor = 0;
        $ledgerTransactionId = null;
        $live = null;

        $hold = DB::transaction(function () use ($holdId, $adminAccountId, $note, &$rewarded, &$accountId, &$amountMinor, &$ledgerTransactionId, &$live): LiveRewardHold {
            $hold = LiveRewardHold::query()->lockForUpdate()->find($holdId);
            if ($hold === null) {
                throw new AppException('LIVE_REWARD_HOLD_NOT_FOUND', 'Cette retenue Live est introuvable.', [], 404);
            }
            if ($hold->status === LiveRewardHold::STATUS_RELEASED) {
                return $hold;
            }
            if ($hold->status === LiveRewardHold::STATUS_REJECTED) {
                throw new AppException('LIVE_REWARD_HOLD_ALREADY_REJECTED', 'Cette retenue a déjà été refusée.', [], 409);
            }

            $block = LiveRewardAttentionBlock::query()->lockForUpdate()->findOrFail($hold->live_reward_attention_block_id);
            $live = LiveEvent::query()->findOrFail($hold->live_id);
            $campaign = $block->campaign()->firstOrFail();

            if ($this->risk->isSelfRewardBlocked($live, (string) $hold->account_id)) {
                $block->update(['status' => LiveRewardAttentionBlock::STATUS_REJECTED]);
                $hold->update([
                    'status' => LiveRewardHold::STATUS_REJECTED,
                    'reviewed_by_account_id' => $adminAccountId,
                    'resolution_note' => trim(($note ? $note.' — ' : '').'Auto-rémunération annonceur interdite.'),
                    'reviewed_at' => now(),
                ]);
                $this->audit($live, $adminAccountId, 'LiveRewardHoldRejected', [
                    'hold_id' => $hold->id,
                    'reason' => 'advertiser_self_reward',
                ]);

                return $hold->refresh();
            }

            $ledgerTransactionId = $this->budgets->captureReward(
                $campaign->advertiser_organization_id,
                $live->id,
                (int) $hold->reward_minor,
                $this->userWallet->availableAccountReference((string) $hold->account_id),
                "live-reward-hold-release:{$hold->id}",
            );

            $block->update([
                'status' => LiveRewardAttentionBlock::STATUS_CAPTURED,
                'ledger_transaction_id' => $ledgerTransactionId,
                'captured_at' => now(),
            ]);
            $hold->update([
                'status' => LiveRewardHold::STATUS_RELEASED,
                'reviewed_by_account_id' => $adminAccountId,
                'resolution_note' => $note,
                'ledger_transaction_id' => $ledgerTransactionId,
                'reviewed_at' => now(),
            ]);

            $rewarded = true;
            $accountId = (string) $hold->account_id;
            $amountMinor = (int) $hold->reward_minor;
            $this->audit($live, $adminAccountId, 'LiveRewardHoldReleased', [
                'hold_id' => $hold->id,
                'block_id' => $block->id,
                'reward_minor' => $amountMinor,
                'ledger_transaction_id' => $ledgerTransactionId,
            ]);

            return $hold->refresh();
        });

        if ($rewarded && $accountId !== null && $ledgerTransactionId !== null) {
            $this->userWallet->notifyBalanceChanged($accountId, $amountMinor, 'live.risk_review', 'credit', $ledgerTransactionId);
        }
        if ($live instanceof LiveEvent && $live->status === LiveEvent::STATUS_ENDED) {
            $this->settlement->settleLive($live, $adminAccountId);
        }

        return $hold->refresh();
    }

    public function reject(string $holdId, string $adminAccountId, ?string $note = null): LiveRewardHold
    {
        $live = null;
        $hold = DB::transaction(function () use ($holdId, $adminAccountId, $note, &$live): LiveRewardHold {
            $hold = LiveRewardHold::query()->lockForUpdate()->find($holdId);
            if ($hold === null) {
                throw new AppException('LIVE_REWARD_HOLD_NOT_FOUND', 'Cette retenue Live est introuvable.', [], 404);
            }
            if ($hold->status === LiveRewardHold::STATUS_REJECTED) {
                return $hold;
            }
            if ($hold->status === LiveRewardHold::STATUS_RELEASED) {
                throw new AppException('LIVE_REWARD_HOLD_ALREADY_RELEASED', 'Cette retenue a déjà été versée.', [], 409);
            }

            $block = LiveRewardAttentionBlock::query()->lockForUpdate()->findOrFail($hold->live_reward_attention_block_id);
            $live = LiveEvent::query()->findOrFail($hold->live_id);
            $block->update(['status' => LiveRewardAttentionBlock::STATUS_REJECTED]);
            $hold->update([
                'status' => LiveRewardHold::STATUS_REJECTED,
                'reviewed_by_account_id' => $adminAccountId,
                'resolution_note' => $note,
                'reviewed_at' => now(),
            ]);
            $this->audit($live, $adminAccountId, 'LiveRewardHoldRejected', [
                'hold_id' => $hold->id,
                'block_id' => $block->id,
            ]);

            return $hold->refresh();
        });

        if ($live instanceof LiveEvent && $live->status === LiveEvent::STATUS_ENDED) {
            $this->settlement->settleLive($live, $adminAccountId);
        }

        return $hold->refresh();
    }

    /** @param array<string, mixed> $metadata */
    private function audit(LiveEvent $live, ?string $actorAccountId, string $eventType, array $metadata = []): void
    {
        LiveAuditEvent::query()->create([
            'live_id' => $live->id,
            'actor_account_id' => $actorAccountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
