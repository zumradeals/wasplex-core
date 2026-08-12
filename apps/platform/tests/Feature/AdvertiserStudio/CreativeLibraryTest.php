<?php

declare(strict_types=1);

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
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

function createStoredVideoAssetForLibraryTests(string $brandId): CreativeAsset
{
    return CreativeAsset::query()->create([
        'brand_id' => $brandId,
        'type' => CreativeAsset::TYPE_VIDEO,
        'filename' => 'spot.mp4',
        'format' => 'mp4',
        'size' => 1024,
        'duration' => 15,
        'duration_ms' => 15000,
        'moderation_status' => CreativeAsset::STATUS_READY,
        'storage_disk' => 'public',
        'storage_path' => 'creatives/spot.mp4',
        'created_by' => 'test-suite',
    ]);
}

it('rejects image uploads because Wasplex V1 is video only', function (): void {
    registerAndLogin('studio-media-image@example.com');
    $brandId = createBrandForLibraryTests();
    test()->postJson('/api/advertiser/assets', [
        'brand_id' => $brandId,
        'file' => File::image('affiche.png', 40, 20),
    ])->assertStatus(422);
});

it('rejects a disallowed file format', function (): void {
    registerAndLogin('studio-media-2@example.com');
    $brandId = createBrandForLibraryTests();
    test()->postJson('/api/advertiser/assets', [
        'brand_id' => $brandId,
        'file' => File::create('malware.exe', 10),
    ])->assertStatus(422);
});

it('never leaks a media asset between two organizations', function (): void {
    registerAndLogin('studio-media-owner@example.com');
    $brandId = createBrandForLibraryTests();
    $assetId = createStoredVideoAssetForLibraryTests($brandId)->id;
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('studio-media-intruder@example.com');
    createAdvertiserOrganization('Org B');
    test()->getJson("/api/advertiser/assets/{$assetId}")->assertStatus(404);
});

it('lets an admin approve a pending video asset', function (): void {
    registerAndLogin('studio-media-mod@example.com');
    $brandId = createBrandForLibraryTests();
    $assetId = createStoredVideoAssetForLibraryTests($brandId)->id;
    $account = Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', 'studio-media-mod@example.com'))
        ->firstOrFail();
    grantFounderAccessForTests($account, ['admin.brands.moderate']);
    verifyRecentMfaForAdvertiserStudioTests();

    test()->postJson("/api/admin/creative-assets/{$assetId}/decide", ['decision' => 'approve'])
        ->assertOk()
        ->assertJsonPath('asset.moderation_status', 'approved');
});
