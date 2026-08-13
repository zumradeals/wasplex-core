<?php

declare(strict_types=1);

use App\Modules\Funds\Application\Services\FundPilotageService;
use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundCollectionParticipant;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundMandate;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundRehabilitationCase;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function p014fAccount(string $email): Account
{
    registerAndLogin($email);
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', $email))->firstOrFail();
    $account->update(['country_code' => 'CI']);
    test()->postJson('/api/logout')->assertNoContent();

    return $account->refresh();
}

function p014fMembership(Account $account, int $rehabilitationThreshold = 1): FundMembership
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();

    DB::table('economic_classes')->insert([
        'id' => $economicClassId,
        'code' => 'P014F-'.Str::upper(Str::random(8)),
        'status' => 'active',
        'created_at' => now(),
    ]);
    DB::table('subscription_plans')->insert([
        'id' => $planId,
        'code' => 'p014f-'.Str::lower(Str::random(8)),
        'name' => 'Abonnement P014-F',
        'created_at' => now(),
    ]);
    DB::table('subscription_plan_versions')->insert([
        'id' => $planVersionId,
        'plan_id' => $planId,
        'status' => 'published',
        'price_minor' => 5000,
        'currency' => 'XOF',
        'duration_days' => 30,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
    ]);

    $subscription = UserSubscription::query()->create([
        'account_id' => $account->id,
        'plan_version_id' => $planVersionId,
        'economic_class_id' => $economicClassId,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now()->subDays(120),
        'current_period_end' => now()->addDays(30),
    ]);
    DB::table('subscription_entitlements')->insert([
        'id' => (string) Str::ulid(),
        'user_subscription_id' => $subscription->id,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE,
        'enabled' => true,
        'created_at' => now(),
    ]);

    $program = FundProgram::query()->create([
        'code' => 'p014f-'.Str::lower(Str::random(8)),
        'name' => 'Programme P014-F',
        'status' => FundProgram::STATUS_ACTIVE,
    ]);
    $version = FundProgramVersion::query()->create([
        'fund_program_id' => $program->id,
        'version' => 1,
        'currency' => 'XOF',
        'duration_days' => 365,
        'max_active_wishes' => 1,
        'max_wishes_per_period' => 2,
        'personal_contribution_percent' => 10,
        'min_debit_minor' => 1,
        'max_debit_minor' => 100000,
        'daily_cap_minor' => 100000,
        'monthly_cap_minor' => 500000,
        'annual_cap_minor' => 5000000,
        'wasplex_fee_minor' => 10,
        'notice_hours' => 24,
        'grace_period_days' => 7,
        'arrears_grace_days' => 7,
        'max_simultaneous_collections' => 1,
        'emergency_queue_share_percent' => 20,
        'reserve_min_balance_minor' => 0,
        'reciprocity_min_score' => 0,
        'rehabilitation_incident_threshold' => $rehabilitationThreshold,
        'status' => 'published',
        'published_at' => now(),
    ]);
    $membership = FundMembership::query()->create([
        'account_id' => $account->id,
        'fund_program_id' => $program->id,
        'program_version_id' => $version->id,
        'user_subscription_id' => $subscription->id,
        'status' => FundMembership::STATUS_ACTIVE,
        'started_at' => now()->subDays(120),
    ]);
    FundMandate::query()->create([
        'fund_membership_id' => $membership->id,
        'personal_cap_minor' => 100000,
        'is_active' => true,
        'terms_version' => 'P014-F-test',
        'accepted_at' => now()->subDays(120),
        'accepted_snapshot' => ['personal_cap_minor' => 100000],
    ]);

    return $membership->load(['account', 'version', 'program', 'mandate', 'subscription.entitlements']);
}

function p014fOverdueArrear(Account $account, FundMembership $membership, int $amount = 500): FundArrear
{
    $category = FundWishCategory::query()->create([
        'code' => 'p014f-cat-'.Str::lower(Str::random(8)),
        'name' => 'Besoin P014-F',
        'is_active' => true,
    ]);
    $wish = FundWish::query()->create([
        'account_id' => $account->id,
        'fund_membership_id' => $membership->id,
        'category_id' => $category->id,
        'status' => FundWish::STATUS_APPROVED,
        'title' => 'Vœu test P014-F',
        'description' => 'Dossier technique utilisé pour tester la réciprocité et la réhabilitation.',
        'country_code' => 'CI',
        'city' => 'Abidjan',
        'estimated_amount_minor' => 10000,
        'validated_amount_minor' => 10000,
        'currency' => 'XOF',
        'required_personal_contribution_minor' => 0,
        'reviewed_at' => now(),
        'cost_validated_at' => now(),
    ]);
    $snapshot = FundCollectionSnapshot::query()->create([
        'fund_wish_id' => $wish->id,
        'fund_program_id' => $membership->fund_program_id,
        'program_version_id' => $membership->program_version_id,
        'country_code' => 'CI',
        'currency' => 'XOF',
        'validated_cost_minor' => 10000,
        'personal_contribution_minor' => 0,
        'collective_amount_minor' => $amount,
        'default_wasplex_fee_minor' => 10,
        'participant_count' => 1,
        'solidarity_base_minor' => $amount,
        'solidarity_remainder_minor' => 0,
        'rule_version' => 'P014-F-test',
        'snapshot_hash' => hash('sha256', (string) Str::ulid()),
        'rules_snapshot' => ['test' => true],
        'status' => FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED,
        'notice_at' => now()->subDays(10),
        'scheduled_at' => now()->subDays(9),
        'started_at' => now()->subDays(9),
        'created_by_account_id' => $account->id,
    ]);
    $participant = FundCollectionParticipant::query()->create([
        'snapshot_id' => $snapshot->id,
        'account_id' => $account->id,
        'fund_membership_id' => $membership->id,
        'fund_mandate_id' => $membership->mandate->id,
        'ordinal' => 1,
        'solidarity_due_minor' => $amount,
        'fee_due_minor' => 10,
        'total_due_minor' => $amount + 10,
        'solidarity_paid_minor' => 0,
        'fee_paid_minor' => 0,
        'arrears_minor' => $amount,
        'mandate_cap_minor' => 100000,
        'rule_snapshot' => ['test' => true],
        'status' => FundCollectionParticipant::STATUS_ARREARS,
        'last_attempted_at' => now()->subDays(9),
    ]);

    return FundArrear::query()->create([
        'snapshot_id' => $snapshot->id,
        'participant_id' => $participant->id,
        'account_id' => $account->id,
        'due_solidarity_minor' => $amount,
        'settled_solidarity_minor' => 0,
        'status' => FundArrear::STATUS_OPEN,
        'grace_ends_at' => now()->subDay(),
    ]);
}

it('P014-F calcule une réciprocité explicable et pénalise un arriéré persistant sans en faire une monnaie', function (): void {
    $account = p014fAccount('p014f-reciprocity@wasplex.test');
    $membership = p014fMembership($account);
    $service = app(FundPilotageService::class);

    $before = $service->refreshReciprocity((string) $account->id);
    p014fOverdueArrear($account, $membership);
    $after = $service->refreshReciprocity((string) $account->id);

    expect((int) $after->score)->toBeLessThan((int) $before->score)
        ->and($after->factors['principles']['not_money'])->toBeTrue()
        ->and($after->factors['principles']['not_purchasable'])->toBeTrue()
        ->and($after->factors['principles']['verified_vital_emergency_overrides_score'])->toBeTrue();
});

it('P014-F suspend seulement Fonds après le seuil puis exige arriérés, entitlement, mandat et programme avant réhabilitation', function (): void {
    $account = p014fAccount('p014f-rehab@wasplex.test');
    $membership = p014fMembership($account, 1);
    $arrear = p014fOverdueArrear($account, $membership);
    $service = app(FundPilotageService::class);

    expect($service->detectRehabilitationCases((string) $account->id))->toBe(1);
    $membership->refresh();
    $case = FundRehabilitationCase::query()->where('fund_membership_id', $membership->id)->firstOrFail();

    expect($membership->status)->toBe(FundMembership::STATUS_SUSPENDED)
        ->and($account->fresh()->status)->toBe('active')
        ->and(FundAuditEvent::query()->where('event_type', 'FundRehabilitationRequired')->where('subject_id', $case->id)->exists())->toBeTrue();

    $arrear->update([
        'status' => FundArrear::STATUS_SETTLED,
        'settled_solidarity_minor' => $arrear->due_solidarity_minor,
        'settled_at' => now(),
    ]);
    DB::table('subscription_entitlements')
        ->where('user_subscription_id', $membership->user_subscription_id)
        ->where('key', SubscriptionEntitlement::KEY_FONDS_ELIGIBLE)
        ->update(['enabled' => false]);

    expect(fn () => $service->completeRehabilitation($case, (string) $account->id, 'Contrôle'))->toThrow(RuntimeException::class);

    DB::table('subscription_entitlements')
        ->where('user_subscription_id', $membership->user_subscription_id)
        ->where('key', SubscriptionEntitlement::KEY_FONDS_ELIGIBLE)
        ->update(['enabled' => true]);
    $completed = $service->completeRehabilitation($case, (string) $account->id, 'Régularisation confirmée');

    expect($completed->status)->toBe(FundRehabilitationCase::STATUS_COMPLETED)
        ->and($membership->fresh()->status)->toBe(FundMembership::STATUS_ACTIVE)
        ->and(FundAuditEvent::query()->where('event_type', 'FundRehabilitationCompleted')->where('subject_id', $case->id)->exists())->toBeTrue();
});

it('P014-F publie uniquement une transparence agrégée sans identité ni donnée médicale', function (): void {
    $data = app(FundPilotageService::class)->transparency();
    $json = json_encode($data, JSON_THROW_ON_ERROR);

    expect($data['privacy']['beneficiary_identity_exposed'])->toBeFalse()
        ->and($data['privacy']['medical_data_exposed'])->toBeFalse()
        ->and($data['privacy']['participant_financial_details_exposed'])->toBeFalse()
        ->and($data['privacy']['wasplex_global_revenue_exposed'])->toBeFalse()
        ->and($json)->not->toContain('phone_e164')
        ->and($json)->not->toContain('beneficiary_account_id');
});
