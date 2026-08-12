<?php

declare(strict_types=1);

use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundQuoteRequest;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Funds\Infrastructure\Models\FundWishQuote;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

function p014cAccount(string $identifier): Account
{
    return Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))->firstOrFail();
}

function p014cLogin(string $identifier): void
{
    test()->withCredentials();
    $response = test()->postJson('/api/login', [
        'identifier_value' => $identifier,
        'password' => 'Password123!',
    ])->assertOk();

    $cookieName = config('session.cookie');
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $cookieName) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }
}

function p014cMfa(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function p014cApprovedWish(Account $account): FundWish
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();
    $subscriptionId = (string) Str::ulid();

    DB::table('economic_classes')->insert(['id' => $economicClassId, 'code' => 'P014C-'.Str::random(8), 'status' => 'active', 'created_at' => now()]);
    DB::table('subscription_plans')->insert(['id' => $planId, 'code' => 'p014c-'.Str::lower(Str::random(8)), 'name' => 'P014-C plan', 'created_at' => now()]);
    DB::table('subscription_plan_versions')->insert([
        'id' => $planVersionId, 'plan_id' => $planId, 'status' => 'published', 'price_minor' => 5000,
        'currency' => 'XOF', 'duration_days' => 30, 'effective_from' => now()->subDay(), 'created_at' => now(),
    ]);
    DB::table('user_subscriptions')->insert([
        'id' => $subscriptionId, 'account_id' => $account->id, 'plan_version_id' => $planVersionId,
        'economic_class_id' => $economicClassId, 'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now()->subDay(), 'current_period_end' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('subscription_entitlements')->insert([
        'id' => (string) Str::ulid(), 'user_subscription_id' => $subscriptionId,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE, 'enabled' => true, 'created_at' => now(),
    ]);

    $program = FundProgram::query()->create(['code' => 'p014c-'.Str::lower(Str::random(8)), 'name' => 'Fonds P014-C', 'status' => FundProgram::STATUS_ACTIVE]);
    $version = FundProgramVersion::query()->create([
        'fund_program_id' => $program->id, 'version' => 1, 'currency' => 'XOF', 'duration_days' => 365,
        'max_active_wishes' => 1, 'max_wishes_per_period' => 2, 'personal_contribution_percent' => 10,
        'min_debit_minor' => 100, 'max_debit_minor' => 10000, 'notice_hours' => 24, 'grace_period_days' => 7,
        'status' => 'published', 'published_at' => now(),
    ]);
    $membership = FundMembership::query()->create([
        'account_id' => $account->id, 'fund_program_id' => $program->id, 'program_version_id' => $version->id,
        'user_subscription_id' => $subscriptionId, 'status' => FundMembership::STATUS_ACTIVE, 'started_at' => now(),
    ]);
    $category = FundWishCategory::query()->create([
        'code' => 'moto-'.Str::lower(Str::random(8)), 'name' => 'Mobilité', 'icon' => 'moto',
        'is_active' => true, 'requires_sensitive_documents' => false,
    ]);

    return FundWish::query()->create([
        'account_id' => $account->id, 'fund_membership_id' => $membership->id, 'category_id' => $category->id,
        'status' => FundWish::STATUS_APPROVED, 'title' => 'Moto pour activité professionnelle',
        'description' => 'Besoin d’une moto fiable pour les déplacements liés à mon activité professionnelle.',
        'country_code' => 'CI', 'city' => 'Abidjan', 'estimated_amount_minor' => 1000000,
        'currency' => 'XOF', 'required_personal_contribution_minor' => 100000, 'reviewed_at' => now(),
    ]);
}

function p014cPartner(string $identifier): array
{
    registerAndLogin($identifier);
    $account = p014cAccount($identifier);
    $response = test()->postJson('/api/professional-spaces', [
        'space_kind' => ProfessionalSpace::KIND_SERVICE_PROVIDER,
        'legal_name' => 'Prestataire P014-C '.Str::random(4),
        'trade_name' => 'Pro Devis',
        'registration_number' => 'P014C-'.Str::random(8),
        'country_code' => 'CI',
        'territory' => ['country_code' => 'CI', 'city' => 'Abidjan'],
        'purposes' => ['Répondre aux demandes de devis Fonds'],
    ])->assertCreated();

    $space = ProfessionalSpace::query()->findOrFail($response->json('space.id'));
    $space->update(['verification_status' => ProfessionalSpace::VERIFICATION_VERIFIED, 'verified_at' => now()]);
    $space->organization()->update(['status' => 'active']);
    $space->userSpace()->update(['status' => 'active']);

    foreach ([
        'professional.space.access',
        'professional.team.manage',
        'professional.service-points.manage',
        'professional.funds.quote.view',
        'professional.funds.quote.respond',
    ] as $capability) {
        CapabilityGrant::query()->create([
            'account_id' => $account->id, 'capability_code' => $capability,
            'scope_type' => CapabilityGrant::SCOPE_ORGANIZATION, 'scope_id' => $space->organization_id,
            'status' => 'active', 'starts_at' => now(), 'granted_by' => $account->id,
        ]);
    }

    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();

    return [$account, $space];
}

function p014cAdmin(): Account
{
    registerAndLogin('p014c-admin@wasplex.test');
    $admin = p014cAccount('p014c-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.funds.view', 'admin.funds.review']);
    p014cMfa();

    return $admin;
}

it('routes a validated wish only to a verified P019 partner and hides beneficiary identity', function (): void {
    registerAndLogin('p014c-member@wasplex.test');
    $member = p014cAccount('p014c-member@wasplex.test');
    $wish = p014cApprovedWish($member);
    test()->postJson('/api/logout')->assertNoContent();

    [, $partnerSpace] = p014cPartner('p014c-partner@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    p014cAdmin();
    test()->postJson("/api/admin/funds/wishes/{$wish->id}/quote-requests", [
        'professional_space_ids' => [$partnerSpace->id],
        'request_summary' => 'Merci de proposer le prix total et le délai de livraison.',
        'expires_at' => now()->addDays(7)->toIso8601String(),
    ])->assertCreated();
    test()->postJson('/api/logout')->assertNoContent();

    p014cLogin('p014c-partner@wasplex.test');
    test()->postJson("/api/me/spaces/{$partnerSpace->user_space_id}/switch")->assertOk();
    $response = test()->getJson('/api/professional/funds/quote-requests')->assertOk();

    $response->assertJsonPath('quote_requests.0.wish.title', 'Moto pour activité professionnelle')
        ->assertJsonMissing(['account_id' => $member->id])
        ->assertJsonMissing(['beneficiary_account_id' => $member->id]);
});

it('lets only the addressed organization answer then selects one real quote and validates cost', function (): void {
    registerAndLogin('p014c-member-select@wasplex.test');
    $member = p014cAccount('p014c-member-select@wasplex.test');
    $wish = p014cApprovedWish($member);
    test()->postJson('/api/logout')->assertNoContent();

    [, $partnerSpace] = p014cPartner('p014c-partner-select@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();
    [, $otherSpace] = p014cPartner('p014c-other-partner@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    $admin = p014cAdmin();
    test()->postJson("/api/admin/funds/wishes/{$wish->id}/quote-requests", [
        'professional_space_ids' => [$partnerSpace->id],
    ])->assertCreated();
    $request = FundQuoteRequest::query()->where('fund_wish_id', $wish->id)->firstOrFail();
    test()->postJson('/api/logout')->assertNoContent();

    p014cLogin('p014c-other-partner@wasplex.test');
    test()->postJson("/api/me/spaces/{$otherSpace->user_space_id}/switch")->assertOk();
    test()->postJson("/api/professional/funds/quote-requests/{$request->id}/quote", [
        'amount_minor' => 850000, 'currency' => 'XOF',
    ])->assertNotFound();
    test()->postJson('/api/logout')->assertNoContent();

    p014cLogin('p014c-partner-select@wasplex.test');
    test()->postJson("/api/me/spaces/{$partnerSpace->user_space_id}/switch")->assertOk();
    test()->postJson("/api/professional/funds/quote-requests/{$request->id}/quote", [
        'amount_minor' => 900000,
        'currency' => 'XOF',
        'lead_time_days' => 5,
        'valid_until' => now()->addDays(10)->toIso8601String(),
        'terms' => 'Moto neuve, livraison et garantie incluses.',
    ])->assertCreated();
    $quote = FundWishQuote::query()->where('quote_request_id', $request->id)->firstOrFail();
    test()->postJson('/api/logout')->assertNoContent();

    p014cLogin('p014c-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.funds.view', 'admin.funds.review']);
    p014cMfa();
    test()->postJson("/api/admin/funds/wishes/{$wish->id}/quotes/{$quote->id}/select")
        ->assertOk()
        ->assertJsonPath('validated_amount_minor', 900000)
        ->assertJsonPath('required_personal_contribution_minor', 90000);

    $wish->refresh();
    expect($wish->selected_quote_id)->toBe($quote->id)
        ->and($wish->provider_organization_id)->toBe($partnerSpace->organization_id)
        ->and($wish->validated_amount_minor)->toBe(900000)
        ->and($quote->fresh()->status)->toBe(FundWishQuote::STATUS_SELECTED);
});

it('grants Funds quote capabilities when a verified partner assigns an operational role', function (): void {
    [, $space] = p014cPartner('p014c-role-owner@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('p014c-role-agent@wasplex.test');
    $agent = p014cAccount('p014c-role-agent@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    DB::table('organization_memberships')->insert([
        'id' => (string) Str::ulid(), 'organization_id' => $space->organization_id, 'account_id' => $agent->id,
        'title' => 'Responsable devis', 'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    p014cLogin('p014c-role-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();
    test()->postJson('/api/professional/roles', [
        'account_id' => $agent->id,
        'role_code' => 'operations_manager',
    ])->assertOk()
        ->assertJsonFragment(['professional.funds.quote.view'])
        ->assertJsonFragment(['professional.funds.quote.respond']);
});
