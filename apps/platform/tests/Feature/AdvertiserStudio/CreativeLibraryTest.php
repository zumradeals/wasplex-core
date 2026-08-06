<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

function createBrandForLibraryTests(): string
{
    createAdvertiserOrganization();

    return test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');
}

it('accepts a valid image upload and reads its dimensions', function (): void {
    registerAndLogin('studio-media-1@example.com');
    $brandId = createBrandForLibraryTests();

    $file = File::image('logo.png', 40, 20);

    test()->postJson('/api/advertiser/assets', ['brand_id' => $brandId, 'file' => $file])
        ->assertCreated()
        ->assertJsonPath('asset.moderation_status', 'ready')
        ->assertJsonPath('asset.width', 40)
        ->assertJsonPath('asset.height', 20);
});

it('rejects a disallowed file format', function (): void {
    registerAndLogin('studio-media-2@example.com');
    $brandId = createBrandForLibraryTests();

    $file = File::create('malware.exe', 10);

    test()->postJson('/api/advertiser/assets', ['brand_id' => $brandId, 'file' => $file])
        ->assertStatus(422);
});

it('rejects a file over the configured size limit', function (): void {
    registerAndLogin('studio-media-3@example.com');
    $brandId = createBrandForLibraryTests();

    $file = File::create('huge.jpg', (config('advertiser_studio.image.max_size_kb') + 100));

    test()->postJson('/api/advertiser/assets', ['brand_id' => $brandId, 'file' => $file])
        ->assertStatus(422);
});

it('never leaks a media asset between two organizations', function (): void {
    registerAndLogin('studio-media-owner@example.com');
    $brandId = createBrandForLibraryTests();
    $file = File::image('logo.png', 40, 20);
    $assetId = test()->postJson('/api/advertiser/assets', ['brand_id' => $brandId, 'file' => $file])->assertCreated()->json('asset.id');

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('studio-media-intruder@example.com');
    createAdvertiserOrganization('Org B');

    test()->getJson("/api/advertiser/assets/{$assetId}")->assertStatus(404);
});

it('lets an admin approve or reject a pending media asset', function (): void {
    registerAndLogin('studio-media-mod@example.com');
    $brandId = createBrandForLibraryTests();
    $file = File::image('logo.png', 40, 20);
    $assetId = test()->postJson('/api/advertiser/assets', ['brand_id' => $brandId, 'file' => $file])->assertCreated()->json('asset.id');

    $account = Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', 'studio-media-mod@example.com'))
        ->firstOrFail();
    grantFounderAccessForTests($account, ['admin.brands.moderate']);
    verifyRecentMfaForAdvertiserStudioTests();

    test()->postJson("/api/admin/creative-assets/{$assetId}/decide", ['decision' => 'approve'])
        ->assertOk()
        ->assertJsonPath('asset.moderation_status', 'approved');
});
