<?php

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Domain\Enums\AssetKind;
use App\Modules\AdvertiserStudio\Domain\Enums\AssetStatus;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

final class CreativeAssetService
{
    /** @param array<string, mixed> $metadata */
    public function store(UserSpace $space, Account $actor, UploadedFile $file, ?Brand $brand, array $metadata): CreativeAsset
    {
        $this->guardSpace($space);

        if ($brand instanceof Brand && $brand->advertiser_space_id !== $space->id) {
            throw ValidationException::withMessages(['brand_id' => 'La marque sélectionnée appartient à un autre espace.']);
        }

        $mime = (string) $file->getMimeType();
        $kind = str_starts_with($mime, 'image/') ? AssetKind::Image : AssetKind::Video;
        $disk = (string) config('advertiser-studio.disk', 'public');
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $filename = Str::lower((string) Str::ulid()).'.'.$extension;
        $directory = 'advertisers/'.$space->id.'/media/'.now()->format('Y/m');
        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename, ['visibility' => 'public']);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('Le média n’a pas pu être enregistré.');
        }

        try {
            return DB::transaction(function () use ($space, $actor, $file, $brand, $metadata, $mime, $kind, $disk, $storedPath): CreativeAsset {
                [$width, $height] = $this->dimensions($file, $kind);
                $checksum = hash_file('sha256', $file->getRealPath());

                if (! is_string($checksum)) {
                    throw new RuntimeException('L’empreinte du média n’a pas pu être calculée.');
                }

                $asset = CreativeAsset::query()->create([
                    'advertiser_space_id' => $space->id,
                    'organization_id' => $space->organization_id,
                    'brand_id' => $brand?->id,
                    'uploaded_by_account_id' => $actor->id,
                    'kind' => $kind,
                    'status' => AssetStatus::Ready,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size_bytes' => (int) $file->getSize(),
                    'disk' => $disk,
                    'path' => $storedPath,
                    'checksum_sha256' => $checksum,
                    'width' => $width,
                    'height' => $height,
                ]);

                $asset->versions()->create([
                    'version' => 1,
                    'label' => $this->nullableString($metadata['label'] ?? null),
                    'alt_text' => $this->nullableString($metadata['alt_text'] ?? null),
                    'usage_context' => $this->nullableString($metadata['usage_context'] ?? null),
                    'created_by_account_id' => $actor->id,
                ]);

                if ($brand instanceof Brand) {
                    $brand->assets()->attach($asset->id, [
                        'id' => (string) Str::ulid(),
                        'role' => 'creative',
                        'sort_order' => 0,
                    ]);
                }

                DB::afterCommit(static fn () => event('advertiser.media.uploaded', [$asset->id, $space->id]));

                return $asset->load(['versions', 'brand']);
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $metadata */
    public function updateMetadata(CreativeAsset $asset, UserSpace $space, Account $actor, array $metadata): CreativeAsset
    {
        $this->guardOwnership($asset, $space);

        DB::transaction(function () use ($asset, $actor, $metadata): void {
            $nextVersion = (int) $asset->versions()->max('version') + 1;
            $asset->versions()->create([
                'version' => $nextVersion,
                'label' => $this->nullableString($metadata['label'] ?? null),
                'alt_text' => $this->nullableString($metadata['alt_text'] ?? null),
                'usage_context' => $this->nullableString($metadata['usage_context'] ?? null),
                'created_by_account_id' => $actor->id,
            ]);
        });

        return $asset->load('versions');
    }

    public function archive(CreativeAsset $asset, UserSpace $space): void
    {
        $this->guardOwnership($asset, $space);

        if ($asset->brands()->wherePivot('role', 'logo')->exists()) {
            throw ValidationException::withMessages([
                'asset' => 'Ce média est utilisé comme logo actif. Choisissez d’abord un autre logo.',
            ]);
        }

        DB::transaction(function () use ($asset): void {
            $disk = $asset->disk;
            $path = $asset->path;
            $asset->forceFill(['status' => AssetStatus::Archived])->save();
            $asset->delete();
            DB::afterCommit(static fn () => Storage::disk($disk)->delete($path));
        });
    }

    /** @return array{0:int|null,1:int|null} */
    private function dimensions(UploadedFile $file, AssetKind $kind): array
    {
        if ($kind !== AssetKind::Image) {
            return [null, null];
        }

        $size = @getimagesize($file->getRealPath());

        if (! is_array($size)) {
            return [null, null];
        }

        return [(int) $size[0], (int) $size[1]];
    }

    private function guardSpace(UserSpace $space): void
    {
        if ($space->kind !== SpaceKind::Advertiser || $space->organization_id === null) {
            throw new LogicException('La médiathèque exige un espace annonceur actif.');
        }
    }

    private function guardOwnership(CreativeAsset $asset, UserSpace $space): void
    {
        $this->guardSpace($space);

        if ($asset->advertiser_space_id !== $space->id) {
            throw ValidationException::withMessages(['asset' => 'Ce média appartient à un autre espace annonceur.']);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
