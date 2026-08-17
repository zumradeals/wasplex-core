<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionState;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardRiskEvent;

final class LiveRewardRiskService
{
    public const MODE_OBSERVE = 'observe';

    public const MODE_ENFORCE = 'enforce';

    public function mode(): string
    {
        return config('live.reward_antifraud_mode') === self::MODE_ENFORCE
            ? self::MODE_ENFORCE
            : self::MODE_OBSERVE;
    }

    public function rewardsEnabled(): bool
    {
        return (bool) config('live.rewards_enabled', true);
    }

    public function isSelfRewardBlocked(LiveEvent $live, string $accountId): bool
    {
        if ((string) $live->owner_account_id === $accountId) {
            return true;
        }

        $organizationId = (string) ($live->advertiser_organization_id ?? '');
        if ($organizationId === '') {
            return false;
        }

        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function bindSession(
        LiveEvent $live,
        LiveRewardCampaign $campaign,
        LiveRewardAttentionState $state,
        ?AccountSession $session,
    ): void {
        if ($session === null || (string) $session->account_id !== (string) $state->account_id) {
            return;
        }

        if ($state->account_session_id !== null && (string) $state->account_session_id !== (string) $session->id) {
            $this->signal($live, $campaign, $state, $session, 'session_changed', LiveRewardRiskEvent::SEVERITY_HIGH);
        }

        if ($state->device_id !== null
            && $session->device_id !== null
            && (string) $state->device_id !== (string) $session->device_id) {
            $this->signal($live, $campaign, $state, $session, 'device_changed', LiveRewardRiskEvent::SEVERITY_HIGH);
        }

        $state->account_session_id = $session->id;
        $state->device_id = $session->device_id;
        $state->save();

        $recentCutoff = now()->subMilliseconds(max(1000, (int) config('live.reward_heartbeat_max_gap_ms', 8000) * 2));
        $concurrent = LiveRewardAttentionState::query()
            ->where('account_id', $state->account_id)
            ->where('live_id', '!=', $live->id)
            ->where('last_heartbeat_qualified', true)
            ->where('last_heartbeat_at', '>=', $recentCutoff)
            ->exists();

        if ($concurrent) {
            $this->signal(
                $live,
                $campaign,
                $state,
                $session,
                'concurrent_rewarded_live',
                LiveRewardRiskEvent::SEVERITY_HIGH,
            );
        }
    }

    public function timingSignal(
        LiveEvent $live,
        LiveRewardCampaign $campaign,
        LiveRewardAttentionState $state,
        ?AccountSession $session,
        string $code,
        int $elapsedMs,
    ): void {
        $severity = LiveRewardRiskEvent::SEVERITY_MEDIUM;

        if ($code === 'heartbeat_rate_abuse') {
            $repeatThreshold = max(2, (int) config('live.reward_risk_repeat_threshold', 3));
            $recentCount = LiveRewardRiskEvent::query()
                ->where('live_id', $live->id)
                ->where('account_id', $state->account_id)
                ->where('signal_code', $code)
                ->where('occurred_at', '>=', now()->subMinutes(10))
                ->count();

            if ($recentCount + 1 >= $repeatThreshold) {
                $severity = LiveRewardRiskEvent::SEVERITY_HIGH;
            }
        }

        $this->signal($live, $campaign, $state, $session, $code, $severity, [
            'elapsed_ms' => $elapsedMs,
        ]);
    }

    public function selfRewardAttempt(
        LiveEvent $live,
        LiveRewardCampaign $campaign,
        LiveRewardAttentionState $state,
        ?AccountSession $session,
    ): void {
        $this->signal(
            $live,
            $campaign,
            $state,
            $session,
            'advertiser_self_reward',
            LiveRewardRiskEvent::SEVERITY_CRITICAL,
        );
    }

    /** @param array<string, mixed> $evidence */
    private function signal(
        LiveEvent $live,
        LiveRewardCampaign $campaign,
        LiveRewardAttentionState $state,
        ?AccountSession $session,
        string $code,
        string $severity,
        array $evidence = [],
    ): void {
        $mode = $this->mode();

        LiveRewardRiskEvent::query()->create([
            'live_reward_campaign_id' => $campaign->id,
            'live_id' => $live->id,
            'account_id' => $state->account_id,
            'live_reward_attention_state_id' => $state->id,
            'account_session_id' => $session?->id,
            'device_id' => $session?->device_id,
            'signal_code' => $code,
            'severity' => $severity,
            'mode' => $mode,
            'evidence' => $evidence === [] ? null : $evidence,
            'occurred_at' => now(),
        ]);

        $state->risk_signal_count = (int) $state->risk_signal_count + 1;
        $state->last_risk_signal_code = $code;

        if ($mode === self::MODE_ENFORCE
            && in_array($severity, [LiveRewardRiskEvent::SEVERITY_HIGH, LiveRewardRiskEvent::SEVERITY_CRITICAL], true)) {
            $state->risk_hold_required = true;
        }

        $state->save();
    }
}
