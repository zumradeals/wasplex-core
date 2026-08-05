<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Services\CapabilityChecker;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;

test('an expired capability grant is denied', function (): void {
    registerAndLogin('expired@wasplex.test');

    /** @var Account $account */
    $account = Account::query()->firstOrFail();

    CapabilityGrant::create([
        'account_id' => $account->id,
        'capability_code' => 'admin.dashboard.view',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->subHour(),
        'granted_by' => $account->id,
    ]);

    expect(app(CapabilityChecker::class)->accountHas($account, 'admin.dashboard.view'))->toBeFalse();
});

test('a revoked capability grant is denied even before its expiry', function (): void {
    registerAndLogin('revoked@wasplex.test');

    /** @var Account $account */
    $account = Account::query()->firstOrFail();

    CapabilityGrant::create([
        'account_id' => $account->id,
        'capability_code' => 'admin.dashboard.view',
        'status' => 'revoked',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addYear(),
        'granted_by' => $account->id,
        'revoked_at' => now(),
    ]);

    expect(app(CapabilityChecker::class)->accountHas($account, 'admin.dashboard.view'))->toBeFalse();
});

test('a capability scoped to one organization does not apply to another', function (): void {
    registerAndLogin('scoped@wasplex.test');

    /** @var Account $account */
    $account = Account::query()->firstOrFail();

    CapabilityGrant::create([
        'account_id' => $account->id,
        'capability_code' => 'organization.manage.self',
        'scope_type' => CapabilityGrant::SCOPE_ORGANIZATION,
        'scope_id' => 'org-a',
        'status' => 'active',
        'starts_at' => now(),
        'granted_by' => $account->id,
    ]);

    $checker = app(CapabilityChecker::class);

    expect($checker->accountHas($account, 'organization.manage.self', 'organization', 'org-a'))->toBeTrue();
    expect($checker->accountHas($account, 'organization.manage.self', 'organization', 'org-b'))->toBeFalse();
});
