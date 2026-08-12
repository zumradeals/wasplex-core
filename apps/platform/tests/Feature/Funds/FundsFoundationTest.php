<?php

declare(strict_types=1);

use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function fundsAccount(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function fundsPaidSubscription(Account $account, bool $eligible = true): string
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();
    $subscriptionId = (string) Str::ulid();

    DB::table('economic_classes')->insert([
        'id' => $economicClassId,
        'code' => 'PREMIUM-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'created_at' => now(),
    ]);

    DB::table('subscription_plans')->insert([
        'id' => $planId,
        'code' => 'premium-'.Str::lower(Str::random(8)),
        'name' => 'Premium test Fonds',
        'created_at' => now(),
    ]);

    DB::table('subscription_plan_versions')->insert([
        'id' => $planVersionId,
        'plan_id' => $planId,
        'status' => 'published',
        'price_minor' => 500000,
        'currency' => 'XOF',
        'duration_days' => 30,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
    ]);

    DB::table('user_subscriptions')->insert([
        'id' => $subscriptionId,
        'account_id' => $account->id,
        'plan_version_id' => $planVersionId,
        'economic_class_id' => $economicClassId,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now()->subDays(30),
        'current_period_end' => now()->addDays(30),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('subscription_entitlements')->insert([
        'id' => (string) Str::ulid(),
        'user_subscription_id' => $subscriptionId,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE,
        'enabled' => $eligible,
        'created_at' => now(),
    ]);

    return $subscriptionId;
}

function fundsProgram(array $overrides = []): array
{
    $program = FundProgram::query()->create([
        'code' => 'solidarite-'.Str::lower(Str::random(8)),
        'name' => 'Fonds Solidarité Test',
        'status' => FundProgram::STATUS_ACTIVE,
        'sort_order' => 1,
    ]);

    $version = FundProgramVersion::query()->create(array_merge([
        'fund_program_id' => $program->id,
        'version' => 1,
        'currency' => 'XOF',
        'membership_fee_minor' => 0,
        'duration_days' => 365,
        'minimum_subscription_age_days' => 0,
        'max_active_wishes' => 1,
        'max_wishes_per_period' => 2,
        'max_wish_amount_minor' => 20000000,
        'personal_contribution_percent' => 10,
        'min_debit_minor' => 1000,
        'max_debit_minor' => 10000,
        'daily_cap_minor' => 10000,
        'monthly_cap_minor' => 50000,
        'annual_cap_minor' => 500000,
        'wasplex_fee_minor' => 0,
        'notice_hours' => 24,
        'grace_period_days' => 7,
        'eligible_subscription_classes' => ['PREMIUM'],
        'status' => 'published',
        'published_at' => now(),
    ], $overrides));

    return [$program, $version];
}

function fundsCategory(): FundWishCategory
{
    return FundWishCategory::query()->create([
        'code' => 'urgence-'.Str::lower(Str::random(8)),
        'name' => 'Urgence familiale',
        'icon' => 'heart',
        'description' => 'Catégorie de test pour les vœux Fonds.',
        'is_active' => true,
        'requires_sensitive_documents' => false,
        'sort_order' => 1,
    ]);
}

it('requires an active eligible paid subscription before joining a fund program', function (): void {
    registerAndLogin('funds-ineligible@wasplex.test');
    $account = fundsAccount('funds-ineligible@wasplex.test');
    fundsPaidSubscription($account, false);
    [$program] = fundsProgram();

    test()->getJson('/api/funds')
        ->assertOk()
        ->assertJsonPath('eligible', false);

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertStatus(422);
});

it('joins with a capped mandate then creates and submits a wish', function (): void {
    registerAndLogin('funds-member@wasplex.test');
    $account = fundsAccount('funds-member@wasplex.test');
    fundsPaidSubscription($account);
    [$program] = fundsProgram();
    $category = fundsCategory();

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 500,
        'accept_mandate' => true,
    ])->assertStatus(422);

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 11000,
        'accept_mandate' => true,
    ])->assertStatus(422);

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertCreated()
        ->assertJsonPath('status', FundMembership::STATUS_ACTIVE)
        ->assertJsonPath('mandate.is_active', true)
        ->assertJsonPath('mandate.personal_cap_minor', 5000);

    $wishId = test()->postJson('/api/funds/wishes', [
        'category_id' => $category->id,
        'title' => 'Réparer le toit familial',
        'description' => 'Le toit familial a subi des dégâts importants et nécessite une réparation rapide.',
        'city' => 'Abidjan',
        'estimated_amount_minor' => 1000000,
    ])->assertCreated()
        ->assertJsonPath('status', FundWish::STATUS_DRAFT)
        ->assertJsonPath('required_personal_contribution_minor', 100000)
        ->json('id');

    test()->postJson("/api/funds/wishes/{$wishId}/submit")
        ->assertOk()
        ->assertJsonPath('status', FundWish::STATUS_SUBMITTED);
});

it('uses the grace period after subscription expiry and reactivates when eligibility returns during grace', function (): void {
    registerAndLogin('funds-grace@wasplex.test');
    $account = fundsAccount('funds-grace@wasplex.test');
    $subscriptionId = fundsPaidSubscription($account);
    [$program] = fundsProgram(['grace_period_days' => 7]);

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertCreated();

    DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
        'status' => UserSubscription::STATUS_EXPIRED,
        'current_period_end' => now()->subMinute(),
        'updated_at' => now(),
    ]);

    test()->getJson('/api/funds')
        ->assertOk()
        ->assertJsonPath('eligible', false)
        ->assertJsonPath('membership.status', FundMembership::STATUS_GRACE_PERIOD);

    $membership = FundMembership::query()->where('account_id', $account->id)->firstOrFail();
    expect($membership->grace_ends_at)->not->toBeNull();
    expect($membership->grace_started_at->diffInDays($membership->grace_ends_at))->toBe(7.0);

    DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
        'status' => UserSubscription::STATUS_ACTIVE,
        'current_period_end' => now()->addDays(30),
        'updated_at' => now(),
    ]);

    test()->getJson('/api/funds')
        ->assertOk()
        ->assertJsonPath('eligible', true)
        ->assertJsonPath('membership.status', FundMembership::STATUS_ACTIVE)
        ->assertJsonPath('membership.grace_ends_at', null);
});

it('pins the membership to the published program version accepted at join time', function (): void {
    registerAndLogin('funds-version@wasplex.test');
    $account = fundsAccount('funds-version@wasplex.test');
    fundsPaidSubscription($account);
    [$program, $versionOne] = fundsProgram();

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertCreated()
        ->assertJsonPath('program_version_id', $versionOne->id);

    $versionTwo = FundProgramVersion::query()->create([
        'fund_program_id' => $program->id,
        'version' => 2,
        'currency' => 'XOF',
        'membership_fee_minor' => 0,
        'duration_days' => 365,
        'minimum_subscription_age_days' => 0,
        'max_active_wishes' => 1,
        'max_wishes_per_period' => 1,
        'personal_contribution_percent' => 20,
        'min_debit_minor' => 2000,
        'max_debit_minor' => 15000,
        'notice_hours' => 48,
        'grace_period_days' => 7,
        'status' => 'published',
        'published_at' => now()->addMinute(),
    ]);

    expect($program->fresh()->publishedVersion()?->id)->toBe($versionTwo->id);
    expect(FundMembership::query()->where('account_id', $account->id)->firstOrFail()->program_version_id)
        ->toBe($versionOne->id);
});
