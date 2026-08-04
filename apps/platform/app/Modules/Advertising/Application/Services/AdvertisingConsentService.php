<?php

namespace App\Modules\Advertising\Application\Services;

use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Domain\Events\AdvertisingConsentDenied;
use App\Modules\Advertising\Domain\Events\AdvertisingConsentGranted;
use App\Modules\Advertising\Domain\Events\AdvertisingConsentWithdrawn;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingConsent;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingPurpose;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvertisingConsentService
{
    /**
     * @return array<int, array{
     *     code:string,
     *     title:string,
     *     description:string,
     *     refusalConsequence:string|null,
     *     required:bool,
     *     version:int,
     *     decision:string|null,
     *     decidedAt:string|null,
     *     active:bool
     * }>
     */
    public function summary(Account $account): array
    {
        $purposes = AdvertisingPurpose::query()
            ->with('currentVersion')
            ->where('status', 'active')
            ->orderBy('code')
            ->get();

        $latest = $this->latestByPurpose($account);

        return $purposes->map(function (AdvertisingPurpose $purpose) use ($latest): array {
            $version = $purpose->currentVersion;
            $decision = $latest->get($purpose->id);

            return [
                'code' => $purpose->code,
                'title' => $version?->title ?? $purpose->code,
                'description' => $version?->description ?? '',
                'refusalConsequence' => $version?->refusal_consequence,
                'required' => (bool) ($version?->consent_required ?? true),
                'version' => (int) ($version?->version ?? 0),
                'decision' => $decision?->status->value,
                'decidedAt' => $decision?->decided_at->toIso8601String(),
                'active' => $this->isDecisionActive($purpose, $decision),
            ];
        })->values()->all();
    }

    /** @param array<string, mixed>|null $context */
    public function decide(
        Account $account,
        string $purposeCode,
        AdvertisingConsentStatus $status,
        string $channel = 'web',
        ?array $context = null,
    ): AdvertisingConsent {
        if (! in_array($status, [
            AdvertisingConsentStatus::Granted,
            AdvertisingConsentStatus::Denied,
            AdvertisingConsentStatus::Withdrawn,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Cette décision de consentement n’est pas autorisée.',
            ]);
        }

        return DB::transaction(function () use ($account, $purposeCode, $status, $channel, $context): AdvertisingConsent {
            $purpose = AdvertisingPurpose::query()
                ->with('currentVersion')
                ->where('code', $purposeCode)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $version = $purpose->currentVersion;

            if ($version === null || $version->state !== 'published') {
                throw ValidationException::withMessages([
                    'purpose' => 'Cette finalité n’est pas actuellement disponible.',
                ]);
            }

            $latest = AdvertisingConsent::query()
                ->where('account_id', $account->id)
                ->where('advertising_purpose_id', $purpose->id)
                ->latest('decided_at')
                ->latest('id')
                ->first();

            if (
                $latest instanceof AdvertisingConsent
                && $latest->advertising_purpose_version_id === $version->id
                && $latest->status === $status
            ) {
                return $latest;
            }

            $consent = AdvertisingConsent::query()->create([
                'account_id' => $account->id,
                'advertising_purpose_id' => $purpose->id,
                'advertising_purpose_version_id' => $version->id,
                'status' => $status,
                'channel' => $channel,
                'context' => $context,
                'decided_at' => now(),
            ]);

            DB::afterCommit(function () use ($account, $purpose, $consent, $status): void {
                $event = match ($status) {
                    AdvertisingConsentStatus::Granted => new AdvertisingConsentGranted(
                        $account->id,
                        $purpose->code,
                        $consent->id,
                    ),
                    AdvertisingConsentStatus::Denied => new AdvertisingConsentDenied(
                        $account->id,
                        $purpose->code,
                        $consent->id,
                    ),
                    AdvertisingConsentStatus::Withdrawn => new AdvertisingConsentWithdrawn(
                        $account->id,
                        $purpose->code,
                        $consent->id,
                    ),
                    default => null,
                };

                if ($event !== null) {
                    event($event);
                }
            });

            return $consent;
        });
    }

    /** @param array<int, string> $purposeCodes */
    public function hasActive(Account $account, array $purposeCodes): bool
    {
        if ($purposeCodes === []) {
            return true;
        }

        $purposes = AdvertisingPurpose::query()
            ->with('currentVersion')
            ->whereIn('code', $purposeCodes)
            ->where('status', 'active')
            ->get();

        if ($purposes->count() !== count(array_unique($purposeCodes))) {
            return false;
        }

        $latest = $this->latestByPurpose($account);

        return $purposes->every(
            fn (AdvertisingPurpose $purpose): bool => $this->isDecisionActive(
                $purpose,
                $latest->get($purpose->id),
            ),
        );
    }

    /** @return Collection<string, AdvertisingConsent> */
    private function latestByPurpose(Account $account): Collection
    {
        /** @var Collection<int, AdvertisingConsent> $history */
        $history = AdvertisingConsent::query()
            ->where('account_id', $account->id)
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->get();

        /** @var Collection<string, AdvertisingConsent> $latest */
        $latest = $history
            ->unique('advertising_purpose_id')
            ->keyBy('advertising_purpose_id');

        return $latest;
    }

    private function isDecisionActive(
        AdvertisingPurpose $purpose,
        ?AdvertisingConsent $decision,
    ): bool {
        $version = $purpose->currentVersion;

        if (
            $version === null
            || $version->state !== 'published'
            || ! $version->consent_required
        ) {
            return $version !== null && $version->state === 'published';
        }

        return $decision instanceof AdvertisingConsent
            && $decision->advertising_purpose_version_id === $version->id
            && $decision->status->isActive()
            && ($decision->expires_at === null || $decision->expires_at->isFuture());
    }
}
