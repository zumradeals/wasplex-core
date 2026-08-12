<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\IdentityVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('a member can submit a private identity verification dossier', function (): void {
    Storage::fake('local');
    registerAndLogin('kyc@wasplex.test');

    test()->getJson('/api/me/identity-verification')
        ->assertOk()
        ->assertJsonPath('verification.status', 'not_started');

    $response = test()->post('/api/me/identity-verification', [
        'first_name' => 'Awa',
        'last_name' => 'Koné',
        'birth_date' => '1995-04-12',
        'document_type' => 'national_id',
        'document_country' => 'CI',
        'document_number' => 'CI-123456789',
        'document_front' => UploadedFile::fake()->image('cni.jpg', 900, 600),
        'selfie' => UploadedFile::fake()->image('selfie.jpg', 600, 600),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('verification.status', 'submitted')
        ->assertJsonPath('verification.has_document_front', true)
        ->assertJsonPath('verification.has_selfie', true)
        ->assertJsonMissingPath('verification.document_number')
        ->assertJsonMissingPath('verification.document_front_path')
        ->assertJsonMissingPath('verification.selfie_path');

    $verification = IdentityVerification::query()->firstOrFail();

    expect($verification->document_number)->toBe('CI-123456789')
        ->and($verification->birth_date)->toBe('1995-04-12');

    $rawNumber = DB::table('identity_verifications')->where('id', $verification->id)->value('document_number');
    expect($rawNumber)->not->toBe('CI-123456789');

    Storage::disk('local')->assertExists($verification->document_front_path);
    Storage::disk('local')->assertExists($verification->selfie_path);

    test()->post('/api/me/identity-verification', [
        'first_name' => 'Awa',
        'last_name' => 'Koné',
        'birth_date' => '1995-04-12',
        'document_type' => 'national_id',
        'document_country' => 'CI',
        'document_number' => 'CI-123456789',
        'document_front' => UploadedFile::fake()->image('cni-2.jpg'),
        'selfie' => UploadedFile::fake()->image('selfie-2.jpg'),
    ])->assertStatus(422)->assertJsonPath('code', 'IDENTITY_VERIFICATION_LOCKED');
});
