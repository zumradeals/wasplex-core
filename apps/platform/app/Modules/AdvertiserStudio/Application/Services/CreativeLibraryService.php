<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeModerationCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * docs/13 §18-21: la bibliothèque créative. Aucun pipeline de traitement
 * asynchrone réel n'existe dans ce dépôt (docs/chantiers/P005-CHANTIER.md
 * §3.3) — la validation technique est donc synchrone et limitée à ce qui
 * est vérifiable sans traitement lourd : extension déclarée, taille,
 * dimensions d'image lues directement depuis le fichier. Un média est
 * accepté (`ready`) ou refusé (`needs_changes`) immédiatement à l'upload,
 * jamais laissé en `processing`.
 */
final class CreativeLibraryService
{
    public function __construct(private readonly BrandService $brands) {}

    public function upload(string $organizationId, string $brandId, UploadedFile $file, string $accountId): CreativeAsset
    {
        $brand = $this->brands->find($organizationId, $brandId);

        if (! $brand->advertiserProfile->canCreate()) {
            throw new StudioCreationRestrictedException($organizationId);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $type = $this->resolveType($extension);
        $config = (array) config("advertiser_studio.{$type}");

        if (! in_array($extension, $config['formats'], true)) {
            throw new InvalidCreativeAssetException("Format « {$extension} » non autorisé pour un {$type}.");
        }

        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > $config['max_size_kb']) {
            throw new InvalidCreativeAssetException("Fichier trop volumineux ({$sizeKb} Ko, maximum {$config['max_size_kb']} Ko).");
        }

        $disk = (string) config('advertiser_studio.disk');
        $path = $file->store("advertiser-studio/brands/{$brand->id}/creative-assets", $disk);

        if ($path === false) {
            throw new InvalidCreativeAssetException('Le fichier n\'a pas pu être enregistré.');
        }

        [$width, $height] = $type === CreativeAsset::TYPE_IMAGE
            ? $this->imageDimensions(Storage::disk($disk)->path($path))
            : [null, null];

        return CreativeAsset::query()->create([
            'brand_id' => $brand->id,
            'type' => $type,
            'filename' => $file->getClientOriginalName(),
            'format' => $extension,
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'rights_status' => 'unknown',
            'moderation_status' => CreativeAsset::STATUS_READY,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'created_by' => $accountId,
        ]);
    }

    public function find(string $organizationId, string $assetId): CreativeAsset
    {
        $asset = CreativeAsset::query()
            ->whereHas('brand.advertiserProfile', fn ($q) => $q->where('organization_id', $organizationId))
            ->find($assetId);

        if ($asset === null) {
            throw new CreativeAssetNotFoundException($assetId);
        }

        return $asset;
    }

    public function delete(string $organizationId, string $assetId): void
    {
        $asset = $this->find($organizationId, $assetId);
        Storage::disk($asset->storage_disk)->delete($asset->storage_path);
        $asset->delete();
    }

    public function moderate(CreativeAsset $asset, string $decision, string $decidedBy, ?string $reason = null): CreativeAsset
    {
        CreativeModerationCase::query()->create([
            'creative_asset_id' => $asset->id,
            'decision' => $decision,
            'reason' => $reason,
            'decided_by' => $decidedBy,
        ]);

        $asset->update(['moderation_status' => match ($decision) {
            CreativeModerationCase::DECISION_APPROVE => CreativeAsset::STATUS_APPROVED,
            CreativeModerationCase::DECISION_REQUEST_CHANGES => CreativeAsset::STATUS_NEEDS_CHANGES,
            CreativeModerationCase::DECISION_REJECT => CreativeAsset::STATUS_REJECTED,
            default => $asset->moderation_status,
        }]);

        return $asset->refresh();
    }

    private function resolveType(string $extension): string
    {
        return in_array($extension, (array) config('advertiser_studio.video.formats'), true)
            ? CreativeAsset::TYPE_VIDEO
            : CreativeAsset::TYPE_IMAGE;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensions(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        return $size === false ? [null, null] : [$size[0], $size[1]];
    }
}
