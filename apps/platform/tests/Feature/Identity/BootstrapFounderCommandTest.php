<?php

use App\Modules\Identity\Console\SeedFounderCommand;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\Artisan;

it('repairs founder membership and capabilities idempotently', function (): void {
    registerAndLogin('admin@wasplex.com');

    $accountId = AccountIdentifier::query()->where('value', 'admin@wasplex.com')->valueOrFail('account_id');
    UserSpace::query()->where('space_type', UserSpace::TYPE_ADMIN)->update(['status' => 'suspended']);

    expect(Artisan::call('identity:bootstrap-founder', ['identifier' => 'ADMIN@WASPLEX.COM']))->toBe(0)
        ->and(Artisan::call('identity:bootstrap-founder', ['identifier' => 'admin@wasplex.com']))->toBe(0);

    $adminSpace = UserSpace::query()->where('space_type', UserSpace::TYPE_ADMIN)->firstOrFail();

    expect($adminSpace->status)->toBe('active')
        ->and(SpaceMembership::query()
            ->where('user_space_id', $adminSpace->id)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->count())->toBe(1)
        ->and(CapabilityGrant::query()
            ->where('account_id', $accountId)
            ->where('capability_code', 'admin.dashboard.view')
            ->where('status', 'active')
            ->count())->toBe(1)
        ->and(CapabilityGrant::query()
            ->where('account_id', $accountId)
            ->whereIn('capability_code', SeedFounderCommand::founderCapabilities())
            ->where('status', 'active')
            ->distinct('capability_code')
            ->count('capability_code'))->toBe(count(SeedFounderCommand::founderCapabilities()));
});

it('fails safely when the founder account does not exist', function (): void {
    expect(Artisan::call('identity:bootstrap-founder', ['identifier' => 'missing@wasplex.com']))->toBe(1);
});
