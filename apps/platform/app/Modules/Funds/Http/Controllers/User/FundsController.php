<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\User;

use App\Modules\Funds\Application\Services\FundContributionService;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundMandate;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundPersonalContribution;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final class FundsController
{
    public function __construct(private readonly FundContributionService $contributions) {}

    public function overview(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $subscription = $this->eligibleSubscription($accountId);
        $membership = FundMembership::query()
            ->with(['program', 'version', 'mandate'])
            ->where('account_id', $accountId)
            ->latest('created_at')
            ->first();

        if ($membership !== null) {
            $this->synchronizeMembershipWithSubscription($membership, $subscription);
            $membership->refresh()->load(['program', 'version', 'mandate']);
        }

        $programs = FundProgram::query()
            ->where('status', FundProgram::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->get()
            ->map(function (FundProgram $program): ?array {
                $version = $program->publishedVersion();
                if ($version === null) {
                    return null;
                }

                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'version_id' => $version->id,
                    'version' => $version->version,
                    'currency' => $version->currency,
                    'membership_fee_minor' => $version->membership_fee_minor,
                    'duration_days' => $version->duration_days,
                    'max_active_wishes' => $version->max_active_wishes,
                    'max_wishes_per_period' => $version->max_wishes_per_period,
                    'max_wish_amount_minor' => $version->max_wish_amount_minor,
                    'personal_contribution_percent' => $version->personal_contribution_percent,
                    'min_debit_minor' => $version->min_debit_minor,
                    'max_debit_minor' => $version->max_debit_minor,
                    'monthly_cap_minor' => $version->monthly_cap_minor,
                    'wasplex_fee_minor' => $version->wasplex_fee_minor,
                    'notice_hours' => $version->notice_hours,
                    'grace_period_days' => $version->grace_period_days,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'eligible' => $subscription !== null,
            'subscription' => $subscription === null ? null : [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end,
            ],
            'membership' => $membership === null ? null : $this->membershipPayload($membership),
            'balances' => [
                'wallet_available_minor' => $this->contributions->walletBalanceMinor($accountId),
                'fund_balance_minor' => $this->contributions->fundBalanceMinor($accountId),
                'unit' => 'WP',
                'wp_to_xof' => 1,
            ],
            'programs' => $programs,
            'categories' => FundWishCategory::query()->where('is_active', true)->orderBy('sort_order')->get([
                'id', 'code', 'name', 'icon', 'description', 'requires_sensitive_documents',
            ]),
            'wishes' => FundWish::query()
                ->with(['category:id,name,icon', 'personalContributions'])
                ->where('account_id', $accountId)
                ->latest()
                ->get()
                ->map(fn (FundWish $wish): array => $this->wishPayload($wish)),
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $subscription = $this->eligibleSubscription($accountId);
        abort_if($subscription === null, 422, 'Un abonnement Wasplex payant éligible est requis.');

        $data = $request->validate([
            'program_id' => ['required', 'string', Rule::exists('fund_programs', 'id')],
            'personal_cap_minor' => ['required', 'integer', 'min:0'],
            'accept_mandate' => ['accepted'],
        ]);

        abort_if(
            FundMembership::query()->where('account_id', $accountId)->whereIn('status', [FundMembership::STATUS_ACTIVE, FundMembership::STATUS_GRACE_PERIOD])->exists(),
            422,
            'Une adhésion Fonds active existe déjà.',
        );

        $program = FundProgram::query()->whereKey($data['program_id'])->where('status', FundProgram::STATUS_ACTIVE)->firstOrFail();
        $version = $program->publishedVersion();
        abort_if($version === null, 422, 'Ce programme ne possède aucune version publiée.');

        $cap = (int) $data['personal_cap_minor'];
        abort_if($cap < (int) $version->min_debit_minor, 422, 'Le plafond personnel est inférieur au minimum du programme.');
        if ($version->max_debit_minor !== null) {
            abort_if($cap > (int) $version->max_debit_minor, 422, 'Le plafond personnel dépasse le maximum du programme.');
        }

        $membership = DB::transaction(function () use ($accountId, $subscription, $program, $version, $cap): FundMembership {
            $membership = FundMembership::query()->create([
                'account_id' => $accountId,
                'fund_program_id' => $program->id,
                'program_version_id' => $version->id,
                'user_subscription_id' => $subscription->id,
                'status' => FundMembership::STATUS_ACTIVE,
                'started_at' => now(),
            ]);

            FundMandate::query()->create([
                'fund_membership_id' => $membership->id,
                'personal_cap_minor' => $cap,
                'is_active' => true,
                'terms_version' => 'P014-A-v1',
                'accepted_at' => now(),
                'accepted_snapshot' => [
                    'program_id' => $program->id,
                    'program_version_id' => $version->id,
                    'min_debit_minor' => $version->min_debit_minor,
                    'max_debit_minor' => $version->max_debit_minor,
                    'monthly_cap_minor' => $version->monthly_cap_minor,
                    'notice_hours' => $version->notice_hours,
                    'grace_period_days' => $version->grace_period_days,
                    'personal_cap_minor' => $cap,
                ],
            ]);

            FundAuditEvent::record($accountId, 'FundMembershipStarted', 'fund_membership', $membership->id, [
                'program_version_id' => $version->id,
            ]);

            return $membership;
        });

        return response()->json($this->membershipPayload($membership->load(['program', 'version', 'mandate'])), 201);
    }

    public function revokeMandate(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $membership = FundMembership::query()->with('mandate')->where('account_id', $accountId)->latest()->firstOrFail();
        abort_if($membership->mandate === null || ! $membership->mandate->is_active, 422, 'Aucun mandat actif.');

        DB::transaction(function () use ($accountId, $membership): void {
            $membership->mandate->update(['is_active' => false, 'revoked_at' => now()]);
            $membership->update([
                'status' => FundMembership::STATUS_SUSPENDED,
                'suspended_at' => now(),
                'suspension_reason' => 'mandate_revoked',
            ]);
            FundAuditEvent::record($accountId, 'FundMandateRevoked', 'fund_membership', $membership->id);
        });

        return response()->json($this->membershipPayload($membership->refresh()->load(['program', 'version', 'mandate'])));
    }

    public function fundBalance(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $this->membershipForFunding($accountId);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        try {
            $transactionId = $this->contributions->transferWalletToFundBalance(
                $accountId,
                (int) $data['amount_minor'],
                $data['idempotency_key'],
                $accountId,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        FundAuditEvent::record($accountId, 'FundBalanceFunded', 'ledger_transaction', $transactionId, [
            'amount_minor' => (int) $data['amount_minor'],
        ]);

        return response()->json([
            'ledger_transaction_id' => $transactionId,
            'wallet_available_minor' => $this->contributions->walletBalanceMinor($accountId),
            'fund_balance_minor' => $this->contributions->fundBalanceMinor($accountId),
            'unit' => 'WP',
        ]);
    }

    public function storeWish(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $membership = $this->activeMembership($accountId);

        $data = $request->validate([
            'category_id' => ['required', 'string', Rule::exists('fund_wish_categories', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:20', 'max:4000'],
            'city' => ['nullable', 'string', 'max:120'],
            'estimated_amount_minor' => ['nullable', 'integer', 'min:1'],
        ]);

        $activeCount = FundWish::query()->where('account_id', $accountId)->whereNotIn('status', [FundWish::STATUS_REJECTED])->count();
        abort_if($activeCount >= (int) $membership->version->max_wishes_per_period, 422, 'Votre quota de vœux pour ce programme est atteint.');

        if ($data['estimated_amount_minor'] ?? null) {
            $max = $membership->version->max_wish_amount_minor;
            abort_if($max !== null && (int) $data['estimated_amount_minor'] > (int) $max, 422, 'Le montant estimé dépasse le plafond du programme.');
        }

        $estimated = isset($data['estimated_amount_minor']) ? (int) $data['estimated_amount_minor'] : null;
        $requiredContribution = $estimated === null ? null : (int) ceil($estimated * ((int) $membership->version->personal_contribution_percent / 100));

        $wish = FundWish::query()->create([
            'account_id' => $accountId,
            'fund_membership_id' => $membership->id,
            'category_id' => $data['category_id'],
            'status' => FundWish::STATUS_DRAFT,
            'title' => $data['title'],
            'description' => $data['description'],
            'country_code' => (string) ($request->user()->country_code ?? 'CI'),
            'city' => $data['city'] ?? null,
            'estimated_amount_minor' => $estimated,
            'currency' => $membership->version->currency,
            'required_personal_contribution_minor' => $requiredContribution,
        ]);

        FundAuditEvent::record($accountId, 'FundWishCreated', 'fund_wish', $wish->id);

        return response()->json($this->wishPayload($wish->load(['category:id,name,icon', 'personalContributions'])), 201);
    }

    public function submitWish(Request $request, FundWish $wish): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        abort_unless($wish->account_id === $accountId, 404);
        $this->activeMembership($accountId);
        abort_if($wish->status !== FundWish::STATUS_DRAFT && $wish->status !== FundWish::STATUS_NEEDS_INFORMATION, 422, 'Ce vœu ne peut pas être soumis dans son état actuel.');

        $wish->update(['status' => FundWish::STATUS_SUBMITTED, 'submitted_at' => now(), 'review_note' => null]);
        FundAuditEvent::record($accountId, 'FundWishSubmitted', 'fund_wish', $wish->id);

        return response()->json($this->wishPayload($wish->refresh()->load(['category:id,name,icon', 'personalContributions'])));
    }

    public function contributeWish(Request $request, FundWish $wish): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        abort_unless($wish->account_id === $accountId, 404);
        $membership = $this->membershipForFunding($accountId);
        $this->assertContributionAllowed($membership, $wish);

        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'source' => ['required', Rule::in([FundPersonalContribution::SOURCE_WALLET, FundPersonalContribution::SOURCE_FUND_BALANCE])],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        try {
            $contribution = $this->contributions->contributeToWish(
                $accountId,
                $wish,
                (int) $data['amount_minor'],
                $data['source'],
                $data['idempotency_key'],
                $accountId,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        FundAuditEvent::record($accountId, 'FundWishPersonalContributionPosted', 'fund_wish', $wish->id, [
            'contribution_id' => $contribution->id,
            'amount_minor' => $contribution->amount_minor,
            'source' => $contribution->source,
            'ledger_transaction_id' => $contribution->ledger_transaction_id,
        ]);

        return response()->json($this->wishPayload($wish->refresh()->load(['category:id,name,icon', 'personalContributions'])));
    }

    private function eligibleSubscription(string $accountId): ?UserSubscription
    {
        return UserSubscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
            ->whereHas('entitlements', fn ($query) => $query
                ->where('key', SubscriptionEntitlement::KEY_FONDS_ELIGIBLE)
                ->where('enabled', true))
            ->latest('started_at')
            ->first();
    }

    private function activeMembership(string $accountId): FundMembership
    {
        $membership = $this->membershipForFunding($accountId);
        abort_if($membership->status !== FundMembership::STATUS_ACTIVE, 422, 'Votre adhésion Fonds n’est pas active.');

        return $membership;
    }

    private function membershipForFunding(string $accountId): FundMembership
    {
        $membership = FundMembership::query()->with(['version', 'mandate'])->where('account_id', $accountId)->latest()->firstOrFail();
        $this->synchronizeMembershipWithSubscription($membership, $this->eligibleSubscription($accountId));
        $membership->refresh()->load(['version', 'mandate']);

        $fundingStatusAllowed = $membership->status === FundMembership::STATUS_ACTIVE
            || $membership->status === FundMembership::STATUS_GRACE_PERIOD
            || ($membership->status === FundMembership::STATUS_SUSPENDED && $membership->suspension_reason === 'subscription_expired');
        abort_unless($fundingStatusAllowed, 422, 'Votre adhésion Fonds ne permet pas cette opération.');
        abort_if($membership->mandate === null || ! $membership->mandate->is_active, 422, 'Votre mandat Fonds n’est pas actif.');

        return $membership;
    }

    private function assertContributionAllowed(FundMembership $membership, FundWish $wish): void
    {
        abort_if($wish->status === FundWish::STATUS_DRAFT || $wish->status === FundWish::STATUS_REJECTED, 422, 'Ce vœu ne peut pas encore recevoir d’apport personnel.');

        if ($membership->status === FundMembership::STATUS_ACTIVE) {
            return;
        }

        $engagedBeforeGrace = $wish->submitted_at !== null
            && $membership->grace_started_at !== null
            && $wish->submitted_at->lessThanOrEqualTo($membership->grace_started_at);
        abort_unless($engagedBeforeGrace, 422, 'Seuls les engagements constitués avant l’expiration de l’abonnement peuvent être régularisés.');
    }

    private function synchronizeMembershipWithSubscription(FundMembership $membership, ?UserSubscription $subscription): void
    {
        if ($subscription !== null) {
            if ($membership->status === FundMembership::STATUS_GRACE_PERIOD && $membership->suspension_reason === 'subscription_expired') {
                $membership->update([
                    'status' => FundMembership::STATUS_ACTIVE,
                    'user_subscription_id' => $subscription->id,
                    'grace_started_at' => null,
                    'grace_ends_at' => null,
                    'suspension_reason' => null,
                ]);
            }

            return;
        }

        if ($membership->status === FundMembership::STATUS_ACTIVE) {
            $days = (int) $membership->version->grace_period_days;
            $membership->update([
                'status' => FundMembership::STATUS_GRACE_PERIOD,
                'grace_started_at' => now(),
                'grace_ends_at' => now()->addDays($days),
                'suspension_reason' => 'subscription_expired',
            ]);
        } elseif ($membership->status === FundMembership::STATUS_GRACE_PERIOD && $membership->grace_ends_at?->isPast()) {
            $membership->update([
                'status' => FundMembership::STATUS_SUSPENDED,
                'suspended_at' => now(),
                'suspension_reason' => 'subscription_expired',
            ]);
        }
    }

    private function membershipPayload(FundMembership $membership): array
    {
        return [
            'id' => $membership->id,
            'status' => $membership->status,
            'program' => ['id' => $membership->program->id, 'name' => $membership->program->name, 'code' => $membership->program->code],
            'program_version_id' => $membership->program_version_id,
            'started_at' => $membership->started_at,
            'grace_started_at' => $membership->grace_started_at,
            'grace_ends_at' => $membership->grace_ends_at,
            'suspension_reason' => $membership->suspension_reason,
            'mandate' => $membership->mandate === null ? null : [
                'is_active' => $membership->mandate->is_active,
                'personal_cap_minor' => $membership->mandate->personal_cap_minor,
                'terms_version' => $membership->mandate->terms_version,
                'accepted_at' => $membership->mandate->accepted_at,
            ],
        ];
    }

    private function wishPayload(FundWish $wish): array
    {
        $contributed = $this->contributions->wishContributionMinor($wish);
        $required = $wish->required_personal_contribution_minor === null ? null : (int) $wish->required_personal_contribution_minor;
        $remaining = $required === null ? null : max(0, $required - $contributed);
        $progress = $required === null || $required <= 0 ? 0 : min(100, (int) floor(($contributed / $required) * 100));

        return [
            'id' => $wish->id,
            'status' => $wish->status,
            'title' => $wish->title,
            'description' => $wish->description,
            'city' => $wish->city,
            'estimated_amount_minor' => $wish->estimated_amount_minor,
            'required_personal_contribution_minor' => $required,
            'personal_contribution_minor' => $contributed,
            'personal_contribution_remaining_minor' => $remaining,
            'personal_contribution_progress_percent' => $progress,
            'personal_contribution_completed_at' => $wish->personal_contribution_completed_at,
            'currency' => $wish->currency,
            'submitted_at' => $wish->submitted_at,
            'review_note' => $wish->review_note,
            'category' => $wish->category === null ? null : ['id' => $wish->category->id, 'name' => $wish->category->name, 'icon' => $wish->category->icon],
            'personal_contributions' => $wish->relationLoaded('personalContributions')
                ? $wish->personalContributions->take(10)->map(fn (FundPersonalContribution $item): array => [
                    'id' => $item->id,
                    'source' => $item->source,
                    'amount_minor' => $item->amount_minor,
                    'currency' => $item->currency,
                    'ledger_transaction_id' => $item->ledger_transaction_id,
                    'created_at' => $item->created_at,
                ])->values()
                : [],
        ];
    }
}
