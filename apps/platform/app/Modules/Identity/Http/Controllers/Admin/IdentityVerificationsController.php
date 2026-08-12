<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\IdentityVerification;
use App\Shared\Http\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IdentityVerificationsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['submitted', 'approved', 'rejected'])],
        ]);

        $status = $data['status'] ?? 'submitted';
        $verifications = IdentityVerification::query()
            ->with(['account.profile', 'account.identifiers'])
            ->where('status', $status)
            ->orderByDesc('submitted_at')
            ->limit(100)
            ->get();

        return new JsonResponse([
            'verifications' => $verifications->map(fn (IdentityVerification $verification) => $this->summary($verification))->values(),
        ]);
    }

    public function show(Request $request, IdentityVerification $verification): JsonResponse
    {
        $verification->load(['account.profile', 'account.identifiers']);

        $this->auditLogger->record('IdentityVerificationViewed', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'identity_verification',
            'resource_id' => $verification->id,
        ], $request);

        return new JsonResponse([
            'verification' => [
                ...$this->summary($verification),
                'birth_date' => $verification->birth_date,
                'document_number' => $verification->document_number,
                'has_document_front' => $verification->document_front_path !== null,
                'has_selfie' => $verification->selfie_path !== null,
            ],
        ]);
    }

    public function document(Request $request, IdentityVerification $verification, string $kind): StreamedResponse|JsonResponse
    {
        if (! in_array($kind, ['document', 'selfie'], true)) {
            return ApiErrorResponse::make($request, 'IDENTITY_DOCUMENT_NOT_FOUND', 'Document introuvable.', status: 404);
        }

        $path = $kind === 'document' ? $verification->document_front_path : $verification->selfie_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return ApiErrorResponse::make($request, 'IDENTITY_DOCUMENT_NOT_FOUND', 'Document introuvable.', status: 404);
        }

        $this->auditLogger->record('IdentityVerificationDocumentViewed', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'identity_verification',
            'resource_id' => $verification->id,
            'document_kind' => $kind,
        ], $request);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, IdentityVerification $verification): JsonResponse
    {
        if ($verification->status !== 'submitted') {
            return ApiErrorResponse::make($request, 'IDENTITY_VERIFICATION_NOT_REVIEWABLE', 'Ce dossier ne peut plus être validé.', status: 422);
        }

        $verification->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by_account_id' => $request->user()?->id,
        ]);

        $this->auditLogger->record('IdentityVerificationApproved', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'identity_verification',
            'resource_id' => $verification->id,
            'subject_account_id' => $verification->account_id,
        ], $request);

        return new JsonResponse(['verification' => ['id' => $verification->id, 'status' => 'approved']]);
    }

    public function reject(Request $request, IdentityVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($verification->status !== 'submitted') {
            return ApiErrorResponse::make($request, 'IDENTITY_VERIFICATION_NOT_REVIEWABLE', 'Ce dossier ne peut plus être refusé.', status: 422);
        }

        $verification->update([
            'status' => 'rejected',
            'rejection_reason' => trim($data['reason']),
            'reviewed_at' => now(),
            'reviewed_by_account_id' => $request->user()?->id,
        ]);

        $this->auditLogger->record('IdentityVerificationRejected', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'identity_verification',
            'resource_id' => $verification->id,
            'subject_account_id' => $verification->account_id,
        ], $request);

        return new JsonResponse(['verification' => ['id' => $verification->id, 'status' => 'rejected']]);
    }

    /** @return array<string, mixed> */
    private function summary(IdentityVerification $verification): array
    {
        return [
            'id' => $verification->id,
            'status' => $verification->status,
            'document_type' => $verification->document_type,
            'document_country' => $verification->document_country,
            'submitted_at' => $verification->submitted_at?->toIso8601String(),
            'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
            'rejection_reason' => $verification->rejection_reason,
            'account' => [
                'id' => $verification->account->id,
                'primary_identifier' => $verification->account->primaryIdentifierLabel(),
                'first_name' => $verification->account->profile?->first_name,
                'last_name' => $verification->account->profile?->last_name,
                'country_code' => $verification->account->country_code,
            ],
        ];
    }
}
