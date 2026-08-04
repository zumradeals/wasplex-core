<?php

namespace App\Modules\AdvertiserStudio\Http\Controllers;

use App\Modules\AdvertiserStudio\Application\Services\AdvertiserProfileService;
use App\Modules\AdvertiserStudio\Application\Services\AdvertiserStudioPresenter;
use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Domain\Enums\BrandStatus;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdvertiserStudioController
{
    public function __construct(
        private AdvertiserProfileService $profiles,
        private BrandService $brands,
        private CreativeAssetService $assets,
        private AdvertiserStudioPresenter $presenter,
        private WalletCatalog $wallets,
        private WalletPresenter $walletPresenter,
    ) {}

    public function dashboard(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $profile = $this->profiles->ensure($space, $account);
        $brands = Brand::query()
            ->where('advertiser_space_id', $space->id)
            ->where('status', BrandStatus::Active)
            ->with(['activeVersion.logoAsset'])
            ->withCount('versions')
            ->latest()
            ->limit(4)
            ->get();
        $assets = CreativeAsset::query()
            ->where('advertiser_space_id', $space->id)
            ->with(['versions', 'brand'])
            ->latest()
            ->limit(6)
            ->get();
        $wallet = $this->wallets->forSpace($space);

        return Inertia::render('Studio/Dashboard', [
            ...$this->baseProps($request, $account, $space),
            'profile' => $this->presenter->profile($profile),
            'wallet' => $this->walletPresenter->wallet($wallet),
            'metrics' => [
                'brands' => Brand::query()->where('advertiser_space_id', $space->id)->where('status', BrandStatus::Active)->count(),
                'media' => CreativeAsset::query()->where('advertiser_space_id', $space->id)->count(),
                'members' => $space->memberships()->where('status', 'active')->count(),
                'profileCompletion' => $this->profiles->completion($profile),
            ],
            'recentBrands' => $brands->map(fn (Brand $brand): array => $this->presenter->brand($brand))->values(),
            'recentAssets' => $assets->map(fn (CreativeAsset $asset): array => $this->presenter->asset($asset))->values(),
        ]);
    }

    public function profile(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $profile = $this->profiles->ensure($space, $account);

        return Inertia::render('Studio/Profile', [
            ...$this->baseProps($request, $account, $space),
            'profile' => $this->presenter->profile($profile),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:120'],
            'website_url' => ['nullable', 'url:http,https', 'max:500'],
            'country_code' => ['required', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->profiles->update($space, $account, $data);

        return redirect()->route('studio.profile')->with('success', 'Le profil annonceur a été enregistré.');
    }

    public function brands(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $brands = Brand::query()
            ->where('advertiser_space_id', $space->id)
            ->with(['activeVersion.logoAsset', 'versions.logoAsset'])
            ->withCount('versions')
            ->latest()
            ->get();
        $logos = CreativeAsset::query()
            ->where('advertiser_space_id', $space->id)
            ->where('kind', 'image')
            ->with(['versions', 'brand'])
            ->latest()
            ->get();

        return Inertia::render('Studio/Brands', [
            ...$this->baseProps($request, $account, $space),
            'brands' => $brands->map(fn (Brand $brand): array => $this->presenter->brand($brand))->values(),
            'logoChoices' => $logos->map(fn (CreativeAsset $asset): array => $this->presenter->asset($asset))->values(),
        ]);
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate($this->brandRules());
        $this->brands->create($space, $account, $data);

        return redirect()->route('studio.brands')->with('success', 'La marque a été créée et publiée dans le Studio.');
    }

    public function updateBrand(Request $request, Brand $brand): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $brand = $this->brand($brand, $space);
        /** @var array<string, mixed> $data */
        $data = $request->validate($this->brandRules());
        $this->brands->update($brand, $space, $account, $data);

        return redirect()->route('studio.brands')->with('success', 'Une nouvelle version de la marque a été publiée.');
    }

    public function archiveBrand(Request $request, Brand $brand): RedirectResponse
    {
        [, $space] = $this->context($request);
        $this->brands->archive($this->brand($brand, $space), $space);

        return redirect()->route('studio.brands')->with('success', 'La marque a été archivée.');
    }

    public function media(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $assets = CreativeAsset::query()
            ->where('advertiser_space_id', $space->id)
            ->with(['versions', 'brand'])
            ->latest()
            ->get();
        $brands = Brand::query()
            ->where('advertiser_space_id', $space->id)
            ->where('status', BrandStatus::Active)
            ->with(['activeVersion.logoAsset'])
            ->withCount('versions')
            ->orderBy('name')
            ->get();

        return Inertia::render('Studio/Media', [
            ...$this->baseProps($request, $account, $space),
            'assets' => $assets->map(fn (CreativeAsset $asset): array => $this->presenter->asset($asset))->values(),
            'brands' => $brands->map(fn (Brand $brand): array => $this->presenter->brand($brand))->values(),
            'limits' => [
                'imageMegabytes' => (int) config('advertiser-studio.maximum_image_kilobytes', 10240) / 1024,
                'videoMegabytes' => (int) config('advertiser-studio.maximum_video_kilobytes', 51200) / 1024,
            ],
        ]);
    }

    public function storeMedia(Request $request): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:51200'],
            'brand_id' => ['nullable', 'ulid'],
            'label' => ['nullable', 'string', 'max:180'],
            'alt_text' => ['nullable', 'string', 'max:300'],
            'usage_context' => ['nullable', 'string', 'max:80'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $this->validateMediaSize($file);
        $brand = isset($data['brand_id'])
            ? Brand::query()->where('advertiser_space_id', $space->id)->findOrFail((string) $data['brand_id'])
            : null;
        $this->assets->store($space, $account, $file, $brand, $data);

        return redirect()->route('studio.media')->with('success', 'Le média a été ajouté à la bibliothèque.');
    }

    public function updateMedia(Request $request, CreativeAsset $asset): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $asset = $this->asset($asset, $space);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:180'],
            'alt_text' => ['nullable', 'string', 'max:300'],
            'usage_context' => ['nullable', 'string', 'max:80'],
        ]);
        $this->assets->updateMetadata($asset, $space, $account, $data);

        return redirect()->route('studio.media')->with('success', 'Les informations du média ont été mises à jour.');
    }

    public function archiveMedia(Request $request, CreativeAsset $asset): RedirectResponse
    {
        [, $space] = $this->context($request);
        $this->assets->archive($this->asset($asset, $space), $space);

        return redirect()->route('studio.media')->with('success', 'Le média a été archivé.');
    }

    /** @return array<string, mixed> */
    private function brandRules(): array
    {
        return [
            'public_name' => ['required', 'string', 'min:2', 'max:160'],
            'slogan' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_asset_id' => ['nullable', 'ulid'],
        ];
    }

    /** @return array{0:Account,1:UserSpace} */
    private function context(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::Advertiser, 403);
        $space->loadMissing(['organization', 'memberships']);

        return [$account, $space];
    }

    /** @return array<string, mixed> */
    private function baseProps(Request $request, Account $account, UserSpace $activeSpace): array
    {
        $account->loadMissing(['profile', 'spaces.organization']);

        return [
            'account' => [
                'id' => $account->id,
                'displayName' => $account->profile?->display_name,
            ],
            'activeSpace' => [
                'id' => $activeSpace->id,
                'kind' => $activeSpace->kind->value,
                'label' => $activeSpace->label,
            ],
            'spaces' => $account->spaces->map(fn (UserSpace $space): array => [
                'id' => $space->id,
                'kind' => $space->kind->value,
                'label' => $space->label,
                'active' => $activeSpace->id === $space->id,
            ])->values(),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }

    private function brand(Brand $brand, UserSpace $space): Brand
    {
        abort_unless($brand->advertiser_space_id === $space->id, 404);

        return $brand;
    }

    private function asset(CreativeAsset $asset, UserSpace $space): CreativeAsset
    {
        abort_unless($asset->advertiser_space_id === $space->id, 404);

        return $asset;
    }

    private function validateMediaSize(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        $limit = str_starts_with($mime, 'image/')
            ? (int) config('advertiser-studio.maximum_image_kilobytes', 10240)
            : (int) config('advertiser-studio.maximum_video_kilobytes', 51200);

        if ((int) ceil(((int) $file->getSize()) / 1024) > $limit) {
            throw ValidationException::withMessages([
                'file' => 'Ce média dépasse la taille autorisée pour son format.',
            ]);
        }
    }
}
