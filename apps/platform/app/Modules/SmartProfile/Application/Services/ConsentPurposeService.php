<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use App\Modules\SmartProfile\Infrastructure\Models\ConsentEvent;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurposeVersion;
use App\Modules\SmartProfile\Infrastructure\Models\UserConsent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * docs/17-donnees-permissions-consentements-techniques-wasplex.md §12/§17 :
 * registre de finalités versionnées, même pattern draft→publish que
 * AdvertisingPriceVersion (P006) — une nouvelle version peut suspendre
 * l'ancienne et, si `requires_new_decision`, invalider les consentements
 * déjà accordés (géré par ConsentService, seul lecteur de cet effet).
 */
final class ConsentPurposeService
{
    /**
     * @return Collection<int, ConsentPurpose>
     */
    public function listAll(): Collection
    {
        return ConsentPurpose::query()->with('versions')->orderBy('code')->get();
    }

    /**
     * @return Collection<int, ConsentPurpose>
     */
    public function listActive(): Collection
    {
        return ConsentPurpose::query()
            ->where('status', ConsentPurpose::STATUS_ACTIVE)
            ->with(['versions' => fn ($query) => $query->where('status', ConsentPurposeVersion::STATUS_PUBLISHED)->orderByDesc('version_number')->limit(1)])
            ->get()
            ->filter(fn (ConsentPurpose $purpose) => $purpose->versions->isNotEmpty())
            ->values();
    }

    /**
     * @param  array{code: string, name: string, description: string, requires_new_decision?: bool}  $data
     */
    public function createDraftVersion(array $data): ConsentPurposeVersion
    {
        return DB::transaction(function () use ($data): ConsentPurposeVersion {
            $purpose = ConsentPurpose::query()->firstOrCreate(['code' => $data['code']]);

            $nextVersionNumber = ((int) $purpose->versions()->max('version_number')) + 1;

            return ConsentPurposeVersion::query()->create([
                'consent_purpose_id' => $purpose->id,
                'version_number' => $nextVersionNumber,
                'name' => $data['name'],
                'description' => $data['description'],
                'requires_new_decision' => $data['requires_new_decision'] ?? false,
                'status' => ConsentPurposeVersion::STATUS_DRAFT,
            ]);
        });
    }

    /**
     * @param  array{name?: string, description?: string, requires_new_decision?: bool}  $data
     */
    public function updateDraftVersion(string $versionId, array $data): ConsentPurposeVersion
    {
        $version = $this->findVersion($versionId);

        if ($version->status !== ConsentPurposeVersion::STATUS_DRAFT) {
            throw new ConsentPurposeVersionNotDraftException;
        }

        $version->fill(array_intersect_key($data, array_flip(['name', 'description', 'requires_new_decision'])));
        $version->save();

        return $version;
    }

    public function publish(string $versionId): ConsentPurposeVersion
    {
        $version = $this->findVersion($versionId);

        return DB::transaction(function () use ($version): ConsentPurposeVersion {
            ConsentPurposeVersion::query()
                ->where('consent_purpose_id', $version->consent_purpose_id)
                ->where('status', ConsentPurposeVersion::STATUS_PUBLISHED)
                ->update(['status' => ConsentPurposeVersion::STATUS_DRAFT]);

            $version->update(['status' => ConsentPurposeVersion::STATUS_PUBLISHED, 'published_at' => now()]);
            $version->purpose->update(['status' => ConsentPurpose::STATUS_ACTIVE]);

            if ($version->requires_new_decision) {
                $this->supersedeGrantedConsentsOnPriorVersions($version);
            }

            return $version->refresh();
        });
    }

    public function suspend(string $purposeId): ConsentPurpose
    {
        $purpose = ConsentPurpose::query()->find($purposeId);

        if ($purpose === null) {
            throw new ConsentPurposeNotFoundException($purposeId);
        }

        $purpose->update(['status' => ConsentPurpose::STATUS_SUSPENDED]);

        return $purpose;
    }

    /**
     * docs/17 §17 : une nouvelle version peut « demander une nouvelle
     * décision ». Le consentement basculé n'est pas un refus mais un
     * doute (`superseded`) — ConsentService le traduit en NOT_DECIDED,
     * jamais en REFUSED.
     */
    private function supersedeGrantedConsentsOnPriorVersions(ConsentPurposeVersion $newVersion): void
    {
        $toSupersede = UserConsent::query()
            ->where('consent_purpose_id', $newVersion->consent_purpose_id)
            ->where('consent_purpose_version_id', '!=', $newVersion->id)
            ->where('status', UserConsent::STATUS_GRANTED)
            ->get();

        foreach ($toSupersede as $consent) {
            $consent->update(['status' => UserConsent::STATUS_SUPERSEDED]);

            ConsentEvent::query()->create([
                'user_consent_id' => $consent->id,
                'account_id' => $consent->account_id,
                'consent_purpose_id' => $newVersion->consent_purpose_id,
                'consent_purpose_version_id' => $consent->consent_purpose_version_id,
                'event_type' => ConsentEvent::TYPE_SUPERSEDED,
                'occurred_at' => Carbon::now('UTC'),
            ]);
        }
    }

    private function findVersion(string $versionId): ConsentPurposeVersion
    {
        $version = ConsentPurposeVersion::query()->with('purpose')->find($versionId);

        if ($version === null) {
            throw new ConsentPurposeNotFoundException($versionId);
        }

        return $version;
    }
}
