<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Identity\Application\Contracts\AccountCountryLookupContract;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Events\LiveRewardSeatBroadcast;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardQuote;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;
use App\Modules\Live\Infrastructure\Models\LiveRewardWaitlistEntry;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use App\Modules\Subscriptions\Application\Services\FreeSubscriptionProvisioner;
use App\Shared\Http\AppException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LiveRewardSeatService
{
    public function __construct(
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly AccountCountryLookupContract $countries,
        private readonly FreeSubscriptionProvisioner $freeSubscription,
    ) {}

    public function configureQuotePlan(
        LiveRewardQuote $quote,
        ?int $rewardedSeatCapacity = null,
        ?int $maxBlocksPerViewer = null,
    ): LiveRewardQuote {
        $maximumBlocksByDuration = max(
            1,
            intdiv($quote->planned_duration_minutes * 60, max(1, $quote->block_duration_seconds)),
        );
        $defaultCapacity = max(1, min($quote->estimated_reach_max, 100));
        $capacity = $rewardedSeatCapacity ?? $defaultCapacity;
        $blocksPerViewer = $maxBlocksPerViewer ?? $maximumBlocksByDuration;

        if ($capacity < 1 || $capacity > 100000) {
            throw new AppException(
                'LIVE_REWARD_SEAT_CAPACITY_INVALID',
                'Choisissez entre 1 et 100 000 places rémunérées.',
            );
        }
        if ($quote->estimated_reach_max > 0 && $capacity > $quote->estimated_reach_max) {
            throw new AppException(
                'LIVE_REWARD_SEAT_CAPACITY_ABOVE_AUDIENCE',
                'Le nombre de places rémunérées ne peut pas dépasser l’audience estimée haute.',
                ['estimated_reach_max' => $quote->estimated_reach_max],
            );
        }
        if ($blocksPerViewer < 1 || $blocksPerViewer > $maximumBlocksByDuration) {
            throw new AppException(
                'LIVE_REWARD_MAX_BLOCKS_INVALID',
                'Le plafond de blocs par spectateur dépasse la durée prévue du Live.',
                ['maximum_blocks_by_duration' => $maximumBlocksByDuration],
            );
        }

        $fundedBlocks = $capacity * $blocksPerViewer;
        if ($fundedBlocks < 1 || $quote->spectator_envelope_minor < $fundedBlocks) {
            throw new AppException(
                'LIVE_REWARD_PLAN_UNDERFUNDED',
                'L’enveloppe spectateurs est insuffisante pour garantir au moins 1 WP par bloc financé.',
                ['funded_blocks' => $fundedBlocks],
            );
        }

        $rewardPerBlock = intdiv($quote->spectator_envelope_minor, $fundedBlocks);
        $allocated = $rewardPerBlock * $fundedBlocks;

        $quote->update([
            'rewarded_seat_capacity' => $capacity,
            'max_blocks_per_viewer' => $blocksPerViewer,
            'funded_blocks' => $fundedBlocks,
            'reward_per_block_minor' => $rewardPerBlock,
            'max_reward_per_viewer_minor' => $rewardPerBlock * $blocksPerViewer,
            'spectator_envelope_remainder_minor' => $quote->spectator_envelope_minor - $allocated,
        ]);

        return $quote->refresh();
    }

    public function admit(LiveEvent $live, Account $viewer, LiveViewerSession $session): array
    {
        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            $session->update([
                'rewarded_status' => LiveViewerSession::REWARDED_NOT_APPLICABLE,
                'economic_class' => null,
            ]);

            return $this->stateForAccount($live, $viewer);
        }

        $this->freeSubscription->ensureFor($viewer->id);

        DB::transaction(function () use ($live, $viewer, $session): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_NON_REWARDED]);

                return;
            }

            $quote = $this->fundedQuote($campaign);
            if ($quote === null || $quote->rewarded_seat_capacity < 1) {
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_NON_REWARDED]);

                return;
            }

            [$eligible, $economicClass] = $this->eligibility($campaign, $viewer);
            $session->update(['economic_class' => $economicClass]);
            if (! $eligible || $economicClass === null) {
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_INELIGIBLE]);

                return;
            }

            $this->expireOffersUnderLock($live, $campaign);
            $existingSeat = LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($existingSeat?->status === LiveRewardSeat::STATUS_ACTIVE) {
                $existingSeat->update(['viewer_session_id' => $session->id]);
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_ACTIVE]);

                return;
            }

            $ownWaitlist = LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($ownWaitlist?->status === LiveRewardWaitlistEntry::STATUS_OFFERED
                && $ownWaitlist->offer_expires_at?->isFuture()) {
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_OFFERED]);

                return;
            }
            if ($ownWaitlist?->status === LiveRewardWaitlistEntry::STATUS_WAITING) {
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_WAITING]);
                $this->promoteWaitingUnderLock($live, $campaign, $quote);

                return;
            }

            $hasQueue = LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->whereIn('status', [
                    LiveRewardWaitlistEntry::STATUS_WAITING,
                    LiveRewardWaitlistEntry::STATUS_OFFERED,
                ])
                ->exists();

            if (! $hasQueue && $this->availableSlots($live, $quote) > 0) {
                $this->activateSeat($live, $campaign, $viewer, $session, $economicClass, $existingSeat);
                $session->update(['rewarded_status' => LiveViewerSession::REWARDED_ACTIVE]);

                return;
            }

            $session->update(['rewarded_status' => LiveViewerSession::REWARDED_NON_REWARDED]);
            $this->promoteWaitingUnderLock($live, $campaign, $quote);
        });

        return $this->stateForAccount($live, $viewer);
    }

    public function joinWaitlist(LiveEvent $live, Account $viewer): array
    {
        $this->assertLiveOpen($live);
        $this->freeSubscription->ensureFor($viewer->id);

        DB::transaction(function () use ($live, $viewer): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                throw new AppException('LIVE_REWARD_NOT_AVAILABLE', 'Ce Live ne propose pas de places rémunérées.', [], 409);
            }
            $quote = $this->fundedQuote($campaign);
            if ($quote === null || $quote->rewarded_seat_capacity < 1) {
                throw new AppException('LIVE_REWARD_NOT_AVAILABLE', 'Ce Live ne propose pas de places rémunérées.', [], 409);
            }

            $session = $this->activeViewerSession($live, $viewer, true);
            [$eligible, $economicClass] = $this->eligibility($campaign, $viewer);
            if (! $eligible || $economicClass === null) {
                throw new AppException(
                    'LIVE_REWARD_NOT_ELIGIBLE',
                    'Votre profil ne correspond pas aux conditions de cette place rémunérée.',
                    [],
                    403,
                );
            }

            $activeSeat = LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveRewardSeat::STATUS_ACTIVE)
                ->exists();
            if ($activeSeat) {
                $session->update([
                    'rewarded_status' => LiveViewerSession::REWARDED_ACTIVE,
                    'economic_class' => $economicClass,
                ]);

                return;
            }

            $this->expireOffersUnderLock($live, $campaign);
            $entry = LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                $entry = LiveRewardWaitlistEntry::query()->create([
                    'live_reward_campaign_id' => $campaign->id,
                    'live_id' => $live->id,
                    'account_id' => $viewer->id,
                    'economic_class' => $economicClass,
                    'status' => LiveRewardWaitlistEntry::STATUS_WAITING,
                    'queued_at' => now(),
                ]);
                $this->audit($live, $viewer->id, 'LiveRewardWaitlistJoined');
            } elseif (! in_array($entry->status, [
                LiveRewardWaitlistEntry::STATUS_WAITING,
                LiveRewardWaitlistEntry::STATUS_OFFERED,
            ], true)) {
                $entry->update([
                    'economic_class' => $economicClass,
                    'status' => LiveRewardWaitlistEntry::STATUS_WAITING,
                    'queued_at' => now(),
                    'offered_at' => null,
                    'offer_expires_at' => null,
                    'resolved_at' => null,
                ]);
                $this->audit($live, $viewer->id, 'LiveRewardWaitlistRejoined');
            }

            $session->update([
                'rewarded_status' => $entry->status === LiveRewardWaitlistEntry::STATUS_OFFERED
                    ? LiveViewerSession::REWARDED_OFFERED
                    : LiveViewerSession::REWARDED_WAITING,
                'economic_class' => $economicClass,
            ]);

            $this->promoteWaitingUnderLock($live, $campaign, $quote);
        });

        return $this->stateForAccount($live, $viewer);
    }

    public function leaveWaitlist(LiveEvent $live, Account $viewer): array
    {
        DB::transaction(function () use ($live, $viewer): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                return;
            }

            $entry = LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->whereIn('status', [
                    LiveRewardWaitlistEntry::STATUS_WAITING,
                    LiveRewardWaitlistEntry::STATUS_OFFERED,
                ])
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                return;
            }

            $entry->update([
                'status' => LiveRewardWaitlistEntry::STATUS_CANCELLED,
                'resolved_at' => now(),
            ]);
            $this->activeViewerSession($live, $viewer)?->update([
                'rewarded_status' => LiveViewerSession::REWARDED_NON_REWARDED,
            ]);
            $this->audit($live, $viewer->id, 'LiveRewardWaitlistLeft');
            $quote = $this->fundedQuote($campaign);
            if ($quote !== null) {
                $this->promoteWaitingUnderLock($live, $campaign, $quote);
            }
        });

        return $this->stateForAccount($live, $viewer);
    }

    public function acceptOffer(LiveEvent $live, Account $viewer): array
    {
        $this->assertLiveOpen($live);
        $this->freeSubscription->ensureFor($viewer->id);

        DB::transaction(function () use ($live, $viewer): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                throw new AppException('LIVE_REWARD_NOT_AVAILABLE', 'La place rémunérée n’est plus disponible.', [], 409);
            }
            $quote = $this->fundedQuote($campaign);
            if ($quote === null) {
                throw new AppException('LIVE_REWARD_NOT_AVAILABLE', 'La place rémunérée n’est plus disponible.', [], 409);
            }

            $this->expireOffersUnderLock($live, $campaign);
            $entry = LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveRewardWaitlistEntry::STATUS_OFFERED)
                ->lockForUpdate()
                ->first();
            if ($entry === null || ! $entry->offer_expires_at?->isFuture()) {
                throw new AppException('LIVE_REWARD_OFFER_EXPIRED', 'Cette proposition de place a expiré.', [], 409);
            }

            $session = $this->activeViewerSession($live, $viewer, true);
            if (LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('status', LiveRewardSeat::STATUS_ACTIVE)
                ->count() >= $quote->rewarded_seat_capacity) {
                throw new AppException('LIVE_REWARD_CAPACITY_FULL', 'Les places rémunérées sont momentanément complètes.', [], 409);
            }

            $seat = LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->lockForUpdate()
                ->first();
            $this->activateSeat($live, $campaign, $viewer, $session, $entry->economic_class, $seat);
            $entry->update([
                'status' => LiveRewardWaitlistEntry::STATUS_ACCEPTED,
                'resolved_at' => now(),
            ]);
            $session->update([
                'rewarded_status' => LiveViewerSession::REWARDED_ACTIVE,
                'economic_class' => $entry->economic_class,
            ]);
            $this->audit($live, $viewer->id, 'LiveRewardSeatOfferAccepted');
            $this->scheduleBroadcast($viewer->id, $live, 'rewarded');
            $this->promoteWaitingUnderLock($live, $campaign, $quote);
        });

        return $this->stateForAccount($live, $viewer);
    }

    public function declineOffer(LiveEvent $live, Account $viewer): array
    {
        DB::transaction(function () use ($live, $viewer): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                return;
            }
            $entry = LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveRewardWaitlistEntry::STATUS_OFFERED)
                ->lockForUpdate()
                ->first();
            if ($entry === null) {
                return;
            }

            $entry->update([
                'status' => LiveRewardWaitlistEntry::STATUS_DECLINED,
                'resolved_at' => now(),
            ]);
            $this->activeViewerSession($live, $viewer)?->update([
                'rewarded_status' => LiveViewerSession::REWARDED_NON_REWARDED,
            ]);
            $this->audit($live, $viewer->id, 'LiveRewardSeatOfferDeclined');
            $quote = $this->fundedQuote($campaign);
            if ($quote !== null) {
                $this->promoteWaitingUnderLock($live, $campaign, $quote);
            }
        });

        return $this->stateForAccount($live, $viewer);
    }

    public function releaseForViewer(LiveEvent $live, Account $viewer, string $reason = 'viewer_left'): void
    {
        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            return;
        }

        DB::transaction(function () use ($live, $viewer, $reason): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                return;
            }

            $seat = LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveRewardSeat::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if ($seat === null) {
                return;
            }

            $seat->update([
                'status' => LiveRewardSeat::STATUS_RELEASED,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);
            $this->audit($live, $viewer->id, 'LiveRewardSeatReleased', ['reason' => $reason]);
            $this->scheduleBroadcast($viewer->id, $live, 'non_rewarded');

            $quote = $this->fundedQuote($campaign);
            if ($quote !== null) {
                $this->promoteWaitingUnderLock($live, $campaign, $quote);
            }
        });
    }

    public function closeLive(LiveEvent $live): void
    {
        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            return;
        }

        DB::transaction(function () use ($live): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                return;
            }
            $now = now();

            LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('status', LiveRewardSeat::STATUS_ACTIVE)
                ->update([
                    'status' => LiveRewardSeat::STATUS_COMPLETED,
                    'released_at' => $now,
                    'release_reason' => 'live_ended',
                ]);
            LiveRewardWaitlistEntry::query()
                ->where('live_id', $live->id)
                ->whereIn('status', [
                    LiveRewardWaitlistEntry::STATUS_WAITING,
                    LiveRewardWaitlistEntry::STATUS_OFFERED,
                ])
                ->update([
                    'status' => LiveRewardWaitlistEntry::STATUS_CANCELLED,
                    'resolved_at' => $now,
                ]);
            $this->audit($live, null, 'LiveRewardSeatsClosed');
        });
    }

    public function stateForAccount(LiveEvent $live, Account $viewer): array
    {
        if ($live->type === LiveEvent::TYPE_SPONSORED) {
            $this->freeSubscription->ensureFor($viewer->id);
        }

        if ($live->type === LiveEvent::TYPE_SPONSORED
            && in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            $this->refreshAvailability($live);
        }

        $plan = LiveRewardSeatPresenter::publicPlan($live);
        if ($plan === null) {
            return [
                'plan' => null,
                'viewer' => [
                    'status' => LiveViewerSession::REWARDED_NOT_APPLICABLE,
                    'economic_class' => null,
                    'offer_expires_at' => null,
                    'can_join_waitlist' => false,
                ],
                'channel' => "live-reward.{$viewer->id}",
            ];
        }

        $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->first();
        [$eligible, $economicClass] = $campaign === null
            ? [false, null]
            : $this->eligibility($campaign, $viewer);

        $seat = LiveRewardSeat::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->first();
        $waitlist = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->first();

        $status = $eligible
            ? LiveViewerSession::REWARDED_ELIGIBLE
            : LiveViewerSession::REWARDED_INELIGIBLE;
        $offerExpiresAt = null;

        if ($seat?->status === LiveRewardSeat::STATUS_ACTIVE) {
            $status = LiveViewerSession::REWARDED_ACTIVE;
        } elseif ($waitlist?->status === LiveRewardWaitlistEntry::STATUS_OFFERED
            && $waitlist->offer_expires_at?->isFuture()) {
            $status = LiveViewerSession::REWARDED_OFFERED;
            $offerExpiresAt = $waitlist->offer_expires_at?->toIso8601String();
        } elseif ($waitlist?->status === LiveRewardWaitlistEntry::STATUS_WAITING) {
            $status = LiveViewerSession::REWARDED_WAITING;
        } elseif ($eligible && in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            $activeSession = $this->activeViewerSession($live, $viewer);
            if ($activeSession !== null && $activeSession->rewarded_status === LiveViewerSession::REWARDED_NON_REWARDED) {
                $status = LiveViewerSession::REWARDED_NON_REWARDED;
            }
        }

        return [
            'plan' => $plan,
            'viewer' => [
                'status' => $status,
                'economic_class' => $economicClass,
                'offer_expires_at' => $offerExpiresAt,
                'can_join_waitlist' => $eligible
                    && in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)
                    && ! in_array($status, [
                        LiveViewerSession::REWARDED_ACTIVE,
                        LiveViewerSession::REWARDED_WAITING,
                        LiveViewerSession::REWARDED_OFFERED,
                    ], true)
                    && $plan['available_rewarded_seats'] === 0,
            ],
            'channel' => "live-reward.{$viewer->id}",
        ];
    }

    private function refreshAvailability(LiveEvent $live): void
    {
        DB::transaction(function () use ($live): void {
            $campaign = $this->lockFundedCampaign($live);
            if ($campaign === null) {
                return;
            }
            $quote = $this->fundedQuote($campaign);
            if ($quote === null) {
                return;
            }
            $this->expireOffersUnderLock($live, $campaign);
            $this->promoteWaitingUnderLock($live, $campaign, $quote);
        });
    }

    private function promoteWaitingUnderLock(
        LiveEvent $live,
        LiveRewardCampaign $campaign,
        LiveRewardQuote $quote,
    ): void {
        if (! in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            return;
        }

        $available = $this->availableSlots($live, $quote);
        if ($available < 1) {
            return;
        }

        $entries = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardWaitlistEntry::STATUS_WAITING)
            ->orderBy('queued_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->limit($available)
            ->get();

        foreach ($entries as $entry) {
            $session = LiveViewerSession::query()
                ->where('live_id', $live->id)
                ->where('account_id', $entry->account_id)
                ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                $entry->update([
                    'status' => LiveRewardWaitlistEntry::STATUS_CANCELLED,
                    'resolved_at' => now(),
                ]);

                continue;
            }

            $offeredAt = now();
            $entry->update([
                'status' => LiveRewardWaitlistEntry::STATUS_OFFERED,
                'offered_at' => $offeredAt,
                'offer_expires_at' => $offeredAt->copy()->addSeconds((int) config('live.reward_offer_seconds', 45)),
            ]);
            $session->update(['rewarded_status' => LiveViewerSession::REWARDED_OFFERED]);
            $this->audit($live, $entry->account_id, 'LiveRewardSeatOffered', [
                'offer_expires_at' => $entry->offer_expires_at?->toIso8601String(),
            ]);
            $this->scheduleBroadcast($entry->account_id, $live, 'offered', [
                'offer_expires_at' => $entry->offer_expires_at?->toIso8601String(),
            ]);
        }
    }

    private function expireOffersUnderLock(LiveEvent $live, LiveRewardCampaign $campaign): void
    {
        $expired = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardWaitlistEntry::STATUS_OFFERED)
            ->where('offer_expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($expired as $entry) {
            $entry->update([
                'status' => LiveRewardWaitlistEntry::STATUS_EXPIRED,
                'resolved_at' => now(),
            ]);
            LiveViewerSession::query()
                ->where('live_id', $live->id)
                ->where('account_id', $entry->account_id)
                ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
                ->update(['rewarded_status' => LiveViewerSession::REWARDED_NON_REWARDED]);
            $this->audit($live, $entry->account_id, 'LiveRewardSeatOfferExpired');
            $this->scheduleBroadcast($entry->account_id, $live, 'non_rewarded');
        }
    }

    private function availableSlots(LiveEvent $live, LiveRewardQuote $quote): int
    {
        $active = LiveRewardSeat::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardSeat::STATUS_ACTIVE)
            ->count();
        $offered = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardWaitlistEntry::STATUS_OFFERED)
            ->where('offer_expires_at', '>', now())
            ->count();

        return max(0, $quote->rewarded_seat_capacity - $active - $offered);
    }

    private function activateSeat(
        LiveEvent $live,
        LiveRewardCampaign $campaign,
        Account $viewer,
        LiveViewerSession $session,
        string $economicClass,
        ?LiveRewardSeat $seat = null,
    ): LiveRewardSeat {
        $attributes = [
            'live_reward_campaign_id' => $campaign->id,
            'viewer_session_id' => $session->id,
            'economic_class' => $economicClass,
            'status' => LiveRewardSeat::STATUS_ACTIVE,
            'activated_at' => now(),
            'released_at' => null,
            'release_reason' => null,
        ];

        if ($seat === null) {
            $seat = LiveRewardSeat::query()->create($attributes + [
                'live_id' => $live->id,
                'account_id' => $viewer->id,
            ]);
        } else {
            $seat->update($attributes);
        }

        $this->audit($live, $viewer->id, 'LiveRewardSeatActivated', [
            'economic_class' => $economicClass,
        ]);
        $this->scheduleBroadcast($viewer->id, $live, 'rewarded');

        return $seat;
    }

    private function eligibility(LiveRewardCampaign $campaign, Account $viewer): array
    {
        $economicClass = $this->economicClasses->classForAccount($viewer->id);
        if ($economicClass === null) {
            return [false, null];
        }

        $segment = $campaign->segment_configuration ?? [];
        $targetClasses = array_map(
            static fn (mixed $code): string => strtoupper((string) $code),
            $segment['economic_classes'] ?? [],
        );
        if ($targetClasses !== [] && ! in_array(strtoupper($economicClass), $targetClasses, true)) {
            return [false, strtoupper($economicClass)];
        }

        $targetCountry = strtoupper((string) ($segment['territory']['country_code'] ?? ''));
        if ($targetCountry !== '') {
            $viewerCountry = strtoupper((string) ($this->countries->countryForAccount($viewer->id) ?? ''));
            if ($viewerCountry !== $targetCountry) {
                return [false, strtoupper($economicClass)];
            }
        }

        return [true, strtoupper($economicClass)];
    }

    private function lockFundedCampaign(LiveEvent $live): ?LiveRewardCampaign
    {
        return LiveRewardCampaign::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardCampaign::STATUS_FUNDS_RESERVED)
            ->lockForUpdate()
            ->first();
    }

    private function fundedQuote(LiveRewardCampaign $campaign): ?LiveRewardQuote
    {
        $reservation = LiveRewardBudgetReservation::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
            ->latest('reserved_at')
            ->first();

        return $reservation?->quote;
    }

    private function activeViewerSession(
        LiveEvent $live,
        Account $viewer,
        bool $required = false,
    ): ?LiveViewerSession {
        $session = LiveViewerSession::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
            ->latest('created_at')
            ->first();

        if ($required && $session === null) {
            throw new AppException(
                'LIVE_VIEWER_SESSION_REQUIRED',
                'Entrez d’abord dans le Live avant de demander une place rémunérée.',
                [],
                409,
            );
        }

        return $session;
    }

    private function assertLiveOpen(LiveEvent $live): void
    {
        if (! in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            throw new AppException('LIVE_NOT_JOINABLE', 'Ce Live n’est pas ouvert aux spectateurs.', [], 409);
        }
    }

    private function scheduleBroadcast(
        string $accountId,
        LiveEvent $live,
        string $status,
        array $extra = [],
    ): void {
        DB::afterCommit(function () use ($accountId, $live, $status, $extra): void {
            try {
                LiveRewardSeatBroadcast::dispatch(
                    $accountId,
                    array_merge([
                        'live_id' => $live->id,
                        'status' => $status,
                    ], $extra),
                );
            } catch (Throwable) {
                // Reverb is a realtime convenience; PostgreSQL remains authoritative.
            }
        });
    }

    private function audit(
        LiveEvent $live,
        ?string $actorAccountId,
        string $eventType,
        array $metadata = [],
    ): void {
        LiveAuditEvent::query()->create([
            'live_id' => $live->id,
            'actor_account_id' => $actorAccountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
