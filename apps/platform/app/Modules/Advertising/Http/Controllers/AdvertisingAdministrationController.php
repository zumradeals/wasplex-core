<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Application\Services\AdvertisingConfigurationService;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingPurpose;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdvertisingAdministrationController
{
    public function __construct(private AdvertisingConfigurationService $configuration) {}

    public function index(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $purposes = AdvertisingPurpose::query()
            ->with('currentVersion')
            ->orderBy('code')
            ->get()
            ->map(static function (AdvertisingPurpose $purpose): array {
                $version = $purpose->currentVersion;

                if ($version === null) {
                    return [
                        'id' => $purpose->id,
                        'code' => $purpose->code,
                        'domain' => $purpose->domain,
                        'status' => $purpose->status,
                        'version' => null,
                        'title' => $purpose->code,
                        'description' => '',
                        'consentRequired' => true,
                        'publishedAt' => null,
                    ];
                }

                return [
                    'id' => $purpose->id,
                    'code' => $purpose->code,
                    'domain' => $purpose->domain,
                    'status' => $purpose->status,
                    'version' => $version->version,
                    'title' => $version->title,
                    'description' => $version->description,
                    'consentRequired' => $version->consent_required,
                    'publishedAt' => $version->published_at?->toIso8601String(),
                ];
            })
            ->values();
        $taxonomies = AdvertisingTaxonomy::query()
            ->with(['questions.currentVersion'])
            ->orderBy('category')
            ->orderBy('label')
            ->get()
            ->map(static function (AdvertisingTaxonomy $taxonomy): array {
                $question = $taxonomy->questions->first(
                    static fn (AdvertisingProfileQuestion $candidate): bool => $candidate->currentVersion !== null,
                );

                return [
                    'id' => $taxonomy->id,
                    'code' => $taxonomy->code,
                    'category' => $taxonomy->category,
                    'label' => $taxonomy->label,
                    'valueType' => $taxonomy->value_type,
                    'status' => $taxonomy->status,
                    'allowedForTargeting' => $taxonomy->allowed_for_targeting,
                    'sensitive' => $taxonomy->sensitive,
                    'question' => $question?->currentVersion?->prompt,
                ];
            })
            ->values();

        return Inertia::render('Admin/AdvertisingConfiguration', [
            ...$this->baseProps($request, $account, $space),
            'configuration' => $this->configuration->runtime(),
            'configurationHistory' => $this->configuration->history(),
            'purposes' => $purposes,
            'taxonomies' => $taxonomies,
            'audit' => $this->configuration->aggregateAudit(30),
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        [$account] = $this->context($request);
        /** @var array{minimum_segment_size:int,estimate_rounding_step:int,frequency_window_hours:int,frequency_limit:int,fatigue_limit:int} $data */
        $data = $request->validate([
            'minimum_segment_size' => ['required', 'integer', 'between:5,100000'],
            'estimate_rounding_step' => ['required', 'integer', 'between:1,10000'],
            'frequency_window_hours' => ['required', 'integer', 'between:1,168'],
            'frequency_limit' => ['required', 'integer', 'between:1,100'],
            'fatigue_limit' => ['required', 'integer', 'between:1,10000'],
        ]);

        if ($data['estimate_rounding_step'] > $data['minimum_segment_size']) {
            throw ValidationException::withMessages([
                'estimate_rounding_step' => 'Le pas d’arrondi ne peut pas dépasser le seuil minimal du segment.',
            ]);
        }

        $version = $this->configuration->publish($account, $data);

        return redirect()
            ->route('advertising.admin.index')
            ->with('success', "La configuration publicitaire version {$version->version} est publiée.");
    }

    public function purposeStatus(
        Request $request,
        AdvertisingPurpose $purpose,
    ): RedirectResponse {
        [$account] = $this->context($request);
        /** @var array{status:string} $data */
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);
        $this->configuration->setPurposeStatus($purpose, $data['status'], $account);

        return redirect()
            ->route('advertising.admin.index')
            ->with('success', "La finalité {$purpose->code} est maintenant {$data['status']}.");
    }

    public function taxonomyStatus(
        Request $request,
        AdvertisingTaxonomy $taxonomy,
    ): RedirectResponse {
        [$account] = $this->context($request);
        /** @var array{status:string} $data */
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);
        $this->configuration->setTaxonomyStatus($taxonomy, $data['status'], $account);

        return redirect()
            ->route('advertising.admin.index')
            ->with('success', "La taxonomie {$taxonomy->code} est maintenant {$data['status']}.");
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
