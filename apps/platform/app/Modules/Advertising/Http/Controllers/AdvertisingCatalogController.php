<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMarket;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSector;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AdvertisingCatalogController
{
    public function index(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $locale = (string) config('profile_intelligence.default_locale', 'fr');
        $markets = AdvertisingMarket::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static fn (AdvertisingMarket $market): array => [
                'code' => $market->code,
                'name' => $market->name,
                'currency' => $market->currency,
                'timezone' => $market->timezone,
                'defaultLocale' => $market->default_locale,
                'supportedLocales' => $market->supported_locales ?? [],
                'default' => $market->is_default,
                'status' => $market->status,
            ])
            ->values();
        $sectors = AdvertisingSector::query()
            ->with(['translations', 'parent.translations'])
            ->withCount('taxonomies')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(static fn (AdvertisingSector $sector): array => [
                'id' => $sector->id,
                'code' => $sector->code,
                'name' => $sector->translatedName($locale),
                'parentId' => $sector->parent_id,
                'parentName' => $sector->parent?->translatedName($locale),
                'status' => $sector->status,
                'icon' => $sector->icon,
                'sortOrder' => $sector->sort_order,
                'allowedForTargeting' => $sector->allowed_for_targeting,
                'taxonomiesCount' => $sector->taxonomies_count,
            ])
            ->values();
        $taxonomies = AdvertisingTaxonomy::query()
            ->with(['sector.translations', 'questions.currentVersion'])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(static function (AdvertisingTaxonomy $taxonomy) use ($locale): array {
                $question = $taxonomy->questions->first(
                    static fn (AdvertisingProfileQuestion $candidate): bool => $candidate->currentVersion !== null,
                );

                return [
                    'id' => $taxonomy->id,
                    'code' => $taxonomy->code,
                    'label' => $taxonomy->label,
                    'sectorId' => $taxonomy->advertising_sector_id,
                    'sectorName' => $taxonomy->sector?->translatedName($locale) ?? 'Non classé',
                    'signalKind' => $taxonomy->signal_kind,
                    'valueType' => $taxonomy->value_type,
                    'countryCodes' => $taxonomy->country_codes ?? [],
                    'status' => $taxonomy->status,
                    'allowedForTargeting' => $taxonomy->allowed_for_targeting,
                    'sensitive' => $taxonomy->sensitive,
                    'userVisible' => $taxonomy->user_visible,
                    'aiUsageMode' => $taxonomy->ai_usage_mode,
                    'freshnessDays' => $taxonomy->default_freshness_days,
                    'question' => $question?->currentVersion?->prompt,
                    'options' => $question?->currentVersion?->options ?? [],
                ];
            })
            ->values();

        return Inertia::render('Admin/AdvertisingCatalog', [
            ...$this->baseProps($request, $account, $space),
            'markets' => $markets,
            'sectors' => $sectors,
            'taxonomies' => $taxonomies,
            'defaults' => [
                'market' => strtoupper((string) config('profile_intelligence.default_market', 'ML')),
                'locale' => $locale,
                'aiEnabled' => (bool) config('profile_intelligence.ai.enabled', false),
            ],
        ]);
    }

    public function storeSector(Request $request): RedirectResponse
    {
        $this->context($request);
        /** @var array{code:string,name:string,description?:string|null,icon?:string|null,parent_id?:string|null,sort_order:int,status:string} $data */
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('advertising_sectors', 'code'),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'string', Rule::exists('advertising_sectors', 'id')],
            'sort_order' => ['required', 'integer', 'between:0,100000'],
            'status' => ['required', 'string', 'in:draft,active'],
        ]);

        DB::transaction(function () use ($data): void {
            $sector = AdvertisingSector::query()->create([
                'code' => $data['code'],
                'parent_id' => $data['parent_id'] ?? null,
                'status' => $data['status'],
                'icon' => $data['icon'] ?? null,
                'sort_order' => $data['sort_order'],
                'allowed_for_targeting' => true,
                'metadata' => [
                    'created_from_admin' => true,
                    'international_ready' => true,
                ],
            ]);
            $sector->translations()->create([
                'locale' => (string) config('profile_intelligence.default_locale', 'fr'),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
        });

        return redirect()
            ->route('advertising.admin.catalog.index')
            ->with('success', "Le secteur {$data['name']} a été créé.");
    }

    public function storeTaxonomy(Request $request): RedirectResponse
    {
        $this->context($request);
        /** @var array{sector_id:string,code:string,label:string,signal_kind:string,prompt:string,help_text:string,privacy_note:string,options:array<int,array{value:string,label:string}>,freshness_days:int,country_codes?:array<int,string>|null,ai_usage_mode:string} $data */
        $data = $request->validate([
            'sector_id' => ['required', 'string', Rule::exists('advertising_sectors', 'id')],
            'code' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('advertising_taxonomies', 'code'),
            ],
            'label' => ['required', 'string', 'max:160'],
            'signal_kind' => [
                'required',
                'string',
                Rule::in(['interest', 'habit', 'preference', 'project', 'need', 'status', 'territory']),
            ],
            'prompt' => ['required', 'string', 'max:600'],
            'help_text' => ['required', 'string', 'max:1000'],
            'privacy_note' => ['required', 'string', 'max:1000'],
            'options' => ['required', 'array', 'between:2,12'],
            'options.*.value' => ['required', 'string', 'max:120', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:160'],
            'freshness_days' => ['required', 'integer', 'between:1,1825'],
            'country_codes' => ['nullable', 'array', 'max:50'],
            'country_codes.*' => ['required', 'string', 'size:2', Rule::exists('advertising_markets', 'code')],
            'ai_usage_mode' => [
                'required',
                'string',
                Rule::in(['forbidden', 'assist_only', 'proposal_with_confirmation']),
            ],
        ]);
        $forbidden = config('profile_intelligence.forbidden_signal_kinds', []);

        if (in_array($data['signal_kind'], $forbidden, true)) {
            throw ValidationException::withMessages([
                'signal_kind' => 'Cette catégorie de signal est techniquement interdite au ciblage commercial.',
            ]);
        }

        DB::transaction(function () use ($data): void {
            $nextSort = (int) AdvertisingTaxonomy::query()->max('sort_order') + 10;
            $taxonomy = AdvertisingTaxonomy::query()->create([
                'advertising_sector_id' => $data['sector_id'],
                'code' => $data['code'],
                'category' => $data['signal_kind'],
                'signal_kind' => $data['signal_kind'],
                'country_codes' => isset($data['country_codes'])
                    ? array_map('strtoupper', $data['country_codes'])
                    : null,
                'label' => $data['label'],
                'value_type' => 'single_choice',
                'allowed_for_targeting' => true,
                'sensitive' => false,
                'user_visible' => true,
                'ai_usage_mode' => $data['ai_usage_mode'],
                'default_freshness_days' => $data['freshness_days'],
                'sort_order' => $nextSort,
                'status' => 'active',
                'metadata' => [
                    'created_from_admin' => true,
                    'versioned_question' => true,
                ],
            ]);
            $question = AdvertisingProfileQuestion::query()->create([
                'advertising_taxonomy_id' => $taxonomy->id,
                'code' => str_replace(['.', '-'], '_', $data['code']),
                'status' => 'active',
                'sort_order' => $nextSort,
            ]);
            $version = $question->versions()->create([
                'version' => 1,
                'state' => 'published',
                'prompt' => $data['prompt'],
                'help_text' => $data['help_text'],
                'privacy_note' => $data['privacy_note'],
                'options' => $data['options'],
                'purpose_codes' => ['advertising_personalization', 'smart_profile_usage'],
                'optional' => true,
                'freshness_days' => $data['freshness_days'],
                'effective_from' => now(),
                'published_at' => now(),
            ]);
            $question->forceFill(['current_version_id' => $version->id])->save();
        });

        return redirect()
            ->route('advertising.admin.catalog.index')
            ->with('success', "Le centre d’intérêt {$data['label']} est publié dans le catalogue.");
    }

    /** @return array{0:Account,1:UserSpace} */
    private function context(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::Administration, 403);

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
            'spaces' => $account->spaces->map(fn (UserSpace $candidate): array => [
                'id' => $candidate->id,
                'kind' => $candidate->kind->value,
                'label' => $candidate->label,
                'active' => $activeSpace->id === $candidate->id,
            ])->values(),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }
}
