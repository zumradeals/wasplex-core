<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeModerationCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Bibliothèque créative V1 : Wasplex démarre avec la vidéo uniquement.
 * La durée réelle est mesurée par ffprobe en millisecondes et devient une
 * donnée économique : le navigateur ne choisit jamais la durée facturée.
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
        $config = (array) config('advertiser_studio.video');

        if (! in_array($extension, $config['formats'], true)) {
            throw new InvalidCreativeAssetException(
                'Wasplex accepte uniquement une vidéo MP4, MOV ou WEBM pour une publicité V1.'
            );
        }

        $mime = strtolower((string) $file->getMimeType());
        if ($mime === '' || ! in_array($mime, (array) ($config['mime_types'] ?? []), true)) {
            throw new InvalidCreativeAssetException('Ce fichier ne peut pas être vérifié comme une vidéo valide.');
        }

        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > $config['max_size_kb']) {
            throw new InvalidCreativeAssetException(
                "Fichier trop volumineux ({$sizeKb} Ko, maximum {$config['max_size_kb']} Ko)."
            );
        }

        $disk = (string) config('advertiser_studio.disk');
        $path = $file->store("advertiser-studio/brands/{$brand->id}/creative-assets", $disk);

        if ($path === false) {
            throw new InvalidCreativeAssetException('Le fichier n\'a pas pu être enregistré.');
        }

        $absolutePath = Storage::disk($disk)->path($path);

        try {
            $durationMs = $this->videoDurationMs($absolutePath);
            $maxDurationMs = ((int) $config['max_duration_seconds']) * 1000;

            if ($durationMs > $maxDurationMs) {
                throw new InvalidCreativeAssetException(
                    'Vidéo trop longue (maximum 5 minutes pour Wasplex V1).'
                );
            }
        } catch (InvalidCreativeAssetException $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return CreativeAsset::query()->create([
            'brand_id' => $brand->id,
            'type' => CreativeAsset::TYPE_VIDEO,
            'filename' => $file->getClientOriginalName(),
            'format' => $extension,
            'size' => $file->getSize(),
            'width' => null,
            'height' => null,
            'duration' => (int) ceil($durationMs / 1000),
            'duration_ms' => $durationMs,
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

    private function videoDurationMs(string $absolutePath): int
    {
        $process = new Process([
            (string) config('advertiser_studio.video.ffprobe_binary'),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $absolutePath,
        ]);
        $process->setTimeout(15);
        $process->run();

        $durationSeconds = (float) trim($process->getOutput());

        if (! $process->isSuccessful() || ! is_finite($durationSeconds) || $durationSeconds <= 0) {
            throw new InvalidCreativeAssetException(
                'La durée de cette vidéo ne peut pas être vérifiée. Vérifiez le fichier puis réessayez.'
            );
        }

        return max(1, (int) ceil($durationSeconds * 1000));
    }
}
