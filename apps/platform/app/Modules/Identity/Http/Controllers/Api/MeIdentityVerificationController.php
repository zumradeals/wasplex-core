<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\IdentityVerification;
use App\Shared\Http\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class MeIdentityVerificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $account->load('profile');

        /** @var IdentityVerification|null $verification */
        $verification = IdentityVerification::query()->where('account_id', $account->id)->first();

        return new JsonResponse([
            'verification' => [
                'status' => $verification?->status ?? 'not_started',
                'document_type' => $verification?->document_type,
                'document_country' => $verification?->document_country ?? $account->country_code,
                'has_document_front' => $verification?->document_front_path !== null,
                'has_selfie' => $verification?->selfie_path !== null,
                'rejection_reason' => $verification?->status === 'rejected' ? $verification->rejection_reason : null,
                'submitted_at' => $verification?->submitted_at?->toIso8601String(),
                'reviewed_at' => $verification?->reviewed_at?->toIso8601String(),
            ],
            'identity' => [
                'first_name' => $account->profile?->first_name,
                'last_name' => $account->profile?->last_name,
                'country_code' => $account->country_code,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'document_type' => ['required', 'string', 'in:national_id,passport,drivers_license,residence_permit'],
            'document_country' => ['required', 'string', 'size:2'],
            'document_number' => ['required', 'string', 'max:120'],
            'document_front' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        /** @var IdentityVerification|null $existing */
        $existing = IdentityVerification::query()->where('account_id', $account->id)->first();

        if ($existing !== null && in_array($existing->status, ['submitted', 'approved'], true)) {
            return ApiErrorResponse::make(
                $request,
                'IDENTITY_VERIFICATION_LOCKED',
                $existing->status === 'approved'
                    ? 'Votre identité est déjà vérifiée.'
                    : 'Votre dossier est déjà en cours de vérification.',
                status: 422,
            );
        }

        $verificationId = $existing?->id ?? (string) Str::ulid();
        $directory = "kyc/{$account->id}/{$verificationId}";

        /** @var UploadedFile $document */
        $document = $data['document_front'];
        /** @var UploadedFile $selfie */
        $selfie = $data['selfie'];

        $documentPath = $document->storeAs($directory, 'document.'.($document->extension() ?: 'bin'), 'local');
        $selfiePath = $selfie->storeAs($directory, 'selfie.'.($selfie->extension() ?: 'bin'), 'local');

        if ($documentPath === false || $selfiePath === false) {
            if (is_string($documentPath)) {
                Storage::disk('local')->delete($documentPath);
            }
            if (is_string($selfiePath)) {
                Storage::disk('local')->delete($selfiePath);
            }

            return ApiErrorResponse::make(
                $request,
                'IDENTITY_DOCUMENT_STORAGE_FAILED',
                'Impossible d’enregistrer les documents pour le moment.',
                status: 503,
            );
        }

        $oldPaths = array_filter([
            $existing?->document_front_path,
            $existing?->selfie_path,
        ]);

        try {
            $account->profile()->updateOrCreate([], [
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
            ]);

            IdentityVerification::query()->updateOrCreate(
                ['account_id' => $account->id],
                [
                    'id' => $verificationId,
                    'reviewed_by_account_id' => null,
                    'status' => 'submitted',
                    'document_type' => $data['document_type'],
                    'document_country' => strtoupper($data['document_country']),
                    'document_number' => trim($data['document_number']),
                    'birth_date' => $data['birth_date'],
                    'document_front_path' => $documentPath,
                    'selfie_path' => $selfiePath,
                    'rejection_reason' => null,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete([$documentPath, $selfiePath]);
            throw $exception;
        }

        foreach ($oldPaths as $path) {
            if ($path !== $documentPath && $path !== $selfiePath) {
                Storage::disk('local')->delete($path);
            }
        }

        return $this->show($request);
    }
}
