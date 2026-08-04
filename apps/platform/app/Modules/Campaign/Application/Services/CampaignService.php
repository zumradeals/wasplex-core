<?php

namespace App\Modules\Campaign\Application\Services;

use App\Modules\AdvertiserStudio\Domain\Enums\AssetStatus;
use App\Modules\AdvertiserStudio\Domain\Enums\BrandStatus;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignAudience;
use App\Modules\Campaign\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignPriceCatalog;
use App\Modules\Campaign\Infrastructure\Models\CampaignQuote;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use App\Modules\Wallet\Application\Data\ReservationRequest;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Domain\Enums\ReservationStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class CampaignService
{
    private const ALLOWED_CLASSES = ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM'];

    public function __construct(
        private WalletCatalog $wallets,
        private WalletReservationContract $reservations,
        private CampaignReviewService $reviews,
    ) {}

    public function ensurePriceCatalog(?Account $actor = null): CampaignPriceCatalog
    {
        $published = CampaignPriceCatalog::query()
            ->where('status', 'published')
            ->latest('version')
            ->first();

        if ($published instanceof CampaignPriceCatalog) {
            return $published;
        }

        return CampaignPriceCatalog::query()->create([
            'version' => 1,
            'name' => 'Catalogue sandbox P006',
            'unit' => 'WP',
            'currency' => 'XOF',
            'cpm_minor' => (int) config('campaign.sandbox_cpm_minor', 1000),
            'minimum_budget_minor' => (int) config('campaign.minimum_budget_minor', 5000),
            'platform_share_basis_points' => 5000,
            'user_share_basis_points' => 5000,
            'status' => 'published',
            'created_by_account_id' => $actor?->id,
            'published_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function create(UserSpace $space, Account $actor, array $data): Campaign
    {
        $this->guardSpace($space);
        $brand = $this->brand($space, (string) $data['brand_id']);

        return DB::transaction(function () use ($space, $actor, $data, $brand): Campaign {
            $campaign = Campaign::query()->create([
                'advertiser_space_id' => $space->id,
                'organization_id' => $space->organization_id,
                'brand_id' => $brand->id,
                'created_by_account_id' => $actor->id,
                'name' => trim((string) $data['name']),
                'status' => 'draft',
                'current_step' => max(1, min(7, (int) ($data['current_step'] ?? 1))),
            ]);

            $version = $this->createVersion($campaign, $space, $actor, $data, 1);
            $campaign->forceFill(['active_version_id' => $version->id])->save();

            DB::afterCommit(static fn () => event('campaign.created', [$campaign->id, $space->id]));

            return $this->load($campaign);
        });
    }

    /** @param array<string, mixed> $data */
    public function save(Campaign $campaign, UserSpace $space, Account $actor, array $data): Campaign
    {
        $this->guardOwnership($campaign, $space);
        if (! in_array($campaign->status, ['draft', 'quoted', 'changes_requested'], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Une campagne financée, soumise ou décidée ne peut plus être modifiée.',
            ]);
        }

        return DB::transaction(function () use ($campaign, $space, $actor, $data): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $isCorrection = $locked->status === 'changes_requested';
            $brand = $this->brand($space, (string) $data['brand_id']);

            if ($isCorrection) {
                $funding = $locked->funding()->with('budgetReservation.walletReservation')->first();
                if (
                    ! $funding instanceof CampaignFunding
                    || $funding->budgetReservation?->status !== 'active'
                    || $funding->budgetReservation?->walletReservation?->status !== ReservationStatus::Active
                ) {
                    throw ValidationException::withMessages([
                        'campaign' => 'La réservation budgétaire de cette correction est introuvable.',
                    ]);
                }
                if ((int) ($data['budget_minor'] ?? 0) !== $funding->amount_minor) {
                    throw ValidationException::withMessages([
                        'budget_minor' => 'Le budget financé ne peut pas être modifié pendant une correction administrative.',
                    ]);
                }
            }

            $nextVersion = (int) CampaignVersion::query()->where('campaign_id', $locked->id)->max('version') + 1;
            $version = $this->createVersion($locked, $space, $actor, $data, $nextVersion);

            if (! $isCorrection) {
                CampaignQuote::query()
                    ->where('campaign_id', $locked->id)
                    ->where('status', 'issued')
                    ->update(['status' => 'void', 'updated_at' => now()]);
            }

            $locked->forceFill([
                'brand_id' => $brand->id,
                'name' => trim((string) $data['name']),
                'active_version_id' => $version->id,
                'status' => $isCorrection ? 'changes_requested' : 'draft',
                'current_step' => $isCorrection
                    ? 7
                    : max(1, min(7, (int) ($data['current_step'] ?? $locked->current_step))),
            ])->save();

            DB::afterCommit(static fn () => event(
                $isCorrection ? 'campaign.correction_saved' : 'campaign.updated',
                [$locked->id, $version->id],
            ));

            return $this->load($locked);
        });
    }

    public function quote(Campaign $campaign, UserSpace $space, Account $actor): CampaignQuote
    {
        $this->guardOwnership($campaign, $space);
        if (! in_array($campaign->status, ['draft', 'quoted'], true)) {
            throw ValidationException::withMessages(['campaign' => 'Cette campagne ne peut plus recevoir de devis.']);
        }

        return DB::transaction(function () use ($campaign, $actor): CampaignQuote {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $version = $locked->activeVersion()->with('creatives')->firstOrFail();
            $this->guardReadyForQuote($version);
            $catalog = $this->ensurePriceCatalog($actor);
            $audience = $this->estimate($locked, $version);
            $amount = max($version->requested_budget_minor, $catalog->minimum_budget_minor);
            $userShare = intdiv($amount * $catalog->user_share_basis_points, 10000);
            $platformShare = $amount - $userShare;
            $impressions = max(1000, intdiv($amount * 1000, max(1, $catalog->cpm_minor)));
            $nextVersion = (int) CampaignQuote::query()->where('campaign_id', $locked->id)->max('version') + 1;

            CampaignQuote::query()
                ->where('campaign_id', $locked->id)
                ->where('status', 'issued')
                ->update(['status' => 'void', 'updated_at' => now()]);

            $quote = CampaignQuote::query()->create([
                'campaign_id' => $locked->id,
                'campaign_version_id' => $version->id,
                'audience_id' => $audience->id,
                'price_catalog_id' => $catalog->id,
                'version' => $nextVersion,
                'status' => 'issued',
                'unit' => $catalog->unit,
                'currency' => $catalog->currency,
                'gross_amount_minor' => $amount,
                'platform_share_minor' => $platformShare,
                'user_share_minor' => $userShare,
                'cpm_minor' => $catalog->cpm_minor,
                'estimated_impressions' => $impressions,
                'issued_at' => now(),
                'expires_at' => now()->addHours((int) config('campaign.quote_validity_hours', 48)),
            ]);

            $locked->forceFill(['status' => 'quoted', 'current_step' => 6])->save();
            DB::afterCommit(static fn () => event('campaign.quoted', [$locked->id, $quote->id]));

            return $quote->load(['audience', 'priceCatalog']);
        });
    }

    public function fund(Campaign $campaign, CampaignQuote $quote, UserSpace $space, Account $actor, ?string $traceId = null): CampaignFunding
    {
        $this->guardOwnership($campaign, $space);
        abort_unless($quote->campaign_id === $campaign->id, 404);

        $existing = CampaignFunding::query()->where('quote_id', $quote->id)->with('budgetReservation')->first();
        if ($existing instanceof CampaignFunding) {
            return $existing;
        }

        if ($quote->status !== 'issued' || $quote->expires_at->isPast()) {
            throw ValidationException::withMessages(['quote' => 'Ce devis n’est plus finançable. Générez un nouveau devis.']);
        }

        return DB::transaction(function () use ($campaign, $quote, $space, $actor, $traceId): CampaignFunding {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $wallet = $this->wallets->forSpace($space);
            $expiresAt = now()->addDays((int) config('campaign.reservation_validity_days', 30));
            $businessReference = "campaign:{$locked->id}:quote:{$quote->id}";
            $walletReservation = $this->reservations->reserve(new ReservationRequest(
                walletId: $wallet->id,
                purpose: 'campaign_budget',
                amountMinor: $quote->gross_amount_minor,
                businessReference: $businessReference,
                idempotencyKey: "campaign-funding:{$quote->id}",
                expiresAt: new DateTimeImmutable($expiresAt->toIso8601String()),
                actorAccountId: $actor->id,
                metadata: [
                    'campaign_id' => $locked->id,
                    'quote_id' => $quote->id,
                    'platform_share_minor' => $quote->platform_share_minor,
                    'user_share_minor' => $quote->user_share_minor,
                ],
                traceId: $traceId,
            ));

            $funding = CampaignFunding::query()->create([
                'campaign_id' => $locked->id,
                'quote_id' => $quote->id,
                'wallet_id' => $wallet->id,
                'amount_minor' => $quote->gross_amount_minor,
                'unit' => $quote->unit,
                'currency' => $quote->currency,
                'status' => 'reserved',
                'reserved_at' => now(),
            ]);
            CampaignBudgetReservation::query()->create([
                'campaign_funding_id' => $funding->id,
                'wallet_reservation_id' => $walletReservation->id,
                'business_reference' => $businessReference,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);

            $quote->forceFill(['status' => 'funded', 'funded_at' => now()])->save();
            $locked->forceFill(['status' => 'funded', 'current_step' => 7])->save();
            DB::afterCommit(static fn () => event('campaign.funded', [$locked->id, $funding->id]));

            return $funding->load('budgetReservation.walletReservation');
        });
    }

    public function submit(Campaign $campaign, UserSpace $space, Account $actor): Campaign
    {
        $this->guardOwnership($campaign, $space);

        return DB::transaction(function () use ($campaign, $actor): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'submitted') {
                return $this->load($locked);
            }

            $previousStatus = $locked->status;
            if (! in_array($previousStatus, ['funded', 'changes_requested'], true)) {
                throw ValidationException::withMessages([
                    'campaign' => 'La campagne doit être financée ou corrigée avant sa soumission.',
                ]);
            }

            $funding = $locked->funding()->with('budgetReservation.walletReservation')->first();
            $allowedFundingStatuses = $previousStatus === 'funded' ? ['reserved'] : ['submitted'];
            if (
                ! $funding instanceof CampaignFunding
                || ! in_array($funding->status, $allowedFundingStatuses, true)
                || $funding->budgetReservation?->status !== 'active'
                || $funding->budgetReservation?->walletReservation?->status !== ReservationStatus::Active
            ) {
                throw ValidationException::withMessages([
                    'campaign' => 'La réservation budgétaire active est introuvable.',
                ]);
            }

            $funding->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
            $locked->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'current_step' => 7])->save();
            $this->reviews->openForSubmission($locked, $actor, $previousStatus);
            DB::afterCommit(static fn () => event('campaign.submitted', [$locked->id]));

            return $this->load($locked);
        });
    }

    public function cancel(Campaign $campaign, UserSpace $space, Account $actor, ?string $traceId = null): Campaign
    {
        $this->guardOwnership($campaign, $space);
        if (in_array($campaign->status, ['submitted', 'changes_requested', 'approved', 'suspended', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Cette campagne est sous contrôle administratif et ne peut plus être annulée par l’annonceur.',
            ]);
        }

        return DB::transaction(function () use ($campaign, $actor, $traceId): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                return $this->load($locked);
            }

            $funding = $locked->funding()->with('budgetReservation')->first();
            if ($funding instanceof CampaignFunding && $funding->status === 'reserved' && $funding->budgetReservation instanceof CampaignBudgetReservation) {
                $this->reservations->release(
                    reservationId: $funding->budgetReservation->wallet_reservation_id,
                    idempotencyKey: "campaign-cancel:{$locked->id}",
                    reason: 'Annulation de la campagne par l’annonceur',
                    actorAccountId: $actor->id,
                    traceId: $traceId,
                );
                $funding->budgetReservation->forceFill(['status' => 'released'])->save();
                $funding->forceFill(['status' => 'released'])->save();
            }

            CampaignQuote::query()->where('campaign_id', $locked->id)->where('status', 'issued')->update([
                'status' => 'void',
                'updated_at' => now(),
            ]);
            $locked->forceFill(['status' => 'cancelled'])->save();
            DB::afterCommit(static fn () => event('campaign.cancelled', [$locked->id]));

            return $this->load($locked);
        });
    }

    /** @param array<string, mixed> $data */
    private function createVersion(Campaign $campaign, UserSpace $space, Account $actor, array $data, int $version): CampaignVersion
    {
        $classes = $this->classes($data['selected_classes'] ?? []);
        $assetIds = $this->assetIds($space, $data['creative_asset_ids'] ?? []);
        $campaignVersion = CampaignVersion::query()->create([
            'campaign_id' => $campaign->id,
            'version' => $version,
            'objective' => trim((string) ($data['objective'] ?? 'notoriety')),
            'headline' => $this->nullableString($data['headline'] ?? null),
            'body' => $this->nullableString($data['body'] ?? null),
            'call_to_action' => $this->nullableString($data['call_to_action'] ?? null),
            'destination_url' => $this->nullableString($data['destination_url'] ?? null),
            'starts_on' => $this->nullableString($data['starts_on'] ?? null),
            'ends_on' => $this->nullableString($data['ends_on'] ?? null),
            'territory_name' => $this->nullableString($data['territory_name'] ?? null),
            'radius_km' => isset($data['radius_km']) ? (int) $data['radius_km'] : null,
            'selected_classes' => $classes,
            'requested_budget_minor' => (int) ($data['budget_minor'] ?? 0),
            'created_by_account_id' => $actor->id,
        ]);

        foreach ($assetIds as $sortOrder => $assetId) {
            DB::table('campaign_creatives')->insert([
                'id' => (string) Str::ulid(),
                'campaign_id' => $campaign->id,
                'campaign_version_id' => $campaignVersion->id,
                'creative_asset_id' => $assetId,
                'sort_order' => $sortOrder,
                'created_at' => now(),
            ]);
        }

        return $campaignVersion->load('creatives');
    }

    private function estimate(Campaign $campaign, CampaignVersion $version): CampaignAudience
    {
        $nextVersion = (int) CampaignAudience::query()->where('campaign_id', $campaign->id)->max('version') + 1;
        $radius = max(1, (int) $version->radius_km);
        $classCount = max(1, count($version->selected_classes));
        $base = (int) config('campaign.planning_reach_per_radius_km', 4000);
        $midpoint = max(1000, (int) round($radius * $base * ($classCount / 4)));
        $minimum = max(500, (int) floor($midpoint * 0.75));
        $maximum = max($minimum, (int) ceil($midpoint * 1.25));

        return CampaignAudience::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_version_id' => $version->id,
            'version' => $nextVersion,
            'estimate_kind' => 'planning',
            'territory_name' => (string) $version->territory_name,
            'radius_km' => $radius,
            'selected_classes' => $version->selected_classes,
            'estimated_min' => $minimum,
            'estimated_max' => $maximum,
            'assumptions' => [
                'source' => 'planning_baseline_p006',
                'personal_data_used' => false,
                'smartprofile_used' => false,
                'notice' => 'Estimation de planification, remplacée par le Matching agrégé à partir de P008.',
            ],
            'estimated_at' => now(),
        ]);
    }

    private function guardReadyForQuote(CampaignVersion $version): void
    {
        $errors = [];
        if ($version->creatives->isEmpty()) {
            $errors['creative_asset_ids'] = 'Ajoutez au moins un contenu publicitaire.';
        }
        if ($version->territory_name === null || $version->radius_km === null) {
            $errors['territory_name'] = 'Renseignez le territoire et son rayon.';
        }
        if ($version->selected_classes === []) {
            $errors['selected_classes'] = 'Sélectionnez au moins une classe.';
        }
        if ($version->requested_budget_minor <= 0) {
            $errors['budget_minor'] = 'Renseignez un budget strictement positif.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function brand(UserSpace $space, string $brandId): Brand
    {
        $brand = Brand::query()
            ->where('advertiser_space_id', $space->id)
            ->where('status', BrandStatus::Active->value)
            ->find($brandId);

        if (! $brand instanceof Brand) {
            throw ValidationException::withMessages(['brand_id' => 'Cette marque active ne fait pas partie de votre Studio.']);
        }

        return $brand;
    }

    /** @return array<int, string> */
    private function assetIds(UserSpace $space, mixed $values): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', is_array($values) ? $values : []))));
        if ($ids === []) {
            return [];
        }

        $valid = CreativeAsset::query()
            ->where('advertiser_space_id', $space->id)
            ->where('status', AssetStatus::Ready->value)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages(['creative_asset_ids' => 'Un contenu sélectionné est absent ou indisponible dans votre médiathèque.']);
        }

        return $valid;
    }

    /** @return array<int, string> */
    private function classes(mixed $values): array
    {
        $classes = array_values(array_unique(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            is_array($values) ? $values : [],
        )));

        foreach ($classes as $class) {
            if (! in_array($class, self::ALLOWED_CLASSES, true)) {
                throw ValidationException::withMessages(['selected_classes' => 'Une classe économique sélectionnée est invalide.']);
            }
        }

        return $classes;
    }

    private function guardSpace(UserSpace $space): void
    {
        if ($space->kind !== SpaceKind::Advertiser || $space->organization_id === null) {
            throw new LogicException('Une campagne exige un espace annonceur rattaché à une organisation.');
        }
    }

    private function guardOwnership(Campaign $campaign, UserSpace $space): void
    {
        $this->guardSpace($space);
        abort_unless($campaign->advertiser_space_id === $space->id, 404);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function load(Campaign $campaign): Campaign
    {
        return $campaign->load([
            'brand.activeVersion.logoAsset',
            'activeVersion.creatives.versions',
            'audiences',
            'quotes.audience',
            'quotes.priceCatalog',
            'funding.budgetReservation.walletReservation',
            'funding.wallet.projection',
            'reviewCases.submitter.profile',
            'reviewCases.decider.profile',
            'reviewCases.task',
            'reviewCases.events.actor.profile',
            'statusEvents.actor.profile',
        ]);
    }
}
