<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdvertisingProfileController
{
    public function __construct(
        private AdvertisingProfileService $profiles,
        private AdvertisingConsentService $consents,
        private AccessAuditor $auditor,
    ) {}

    public function index(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $this->auditor->record(
            request: $request,
            action: 'advertising.profile.viewed',
            outcome: 'success',
            capability: 'advertising.profile.view.self',
        );

        return Inertia::render('User/AdvertisingProfile', [
            ...$this->baseProps($account, $space),
            'profile' => $this->profiles->profile($account),
            'consents' => $this->consents->summary($account),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
            'errors' => $request->session()->get('errors')?->getBag('default')->getMessages() ?? [],
        ]);
    }

    public function answer(Request $request): RedirectResponse
    {
        [$account] = $this->context($request);
        /** @var array{question_code:string,answer:string} $validated */
        $validated = $request->validate([
            'question_code' => ['required', 'string', 'max:160'],
            'answer' => ['required', 'string', 'max:180'],
        ]);
        $answer = $this->profiles->answer(
            $account,
            $validated['question_code'],
            $validated['answer'],
        );
        $this->auditor->record(
            request: $request,
            action: 'advertising.profile.answer_recorded',
            outcome: 'success',
            capability: 'advertising.profile.manage.self',
            context: [
                'question_code' => $validated['question_code'],
                'answer_version' => $answer->version,
            ],
        );

        return back()->with('success', 'Votre réponse a été enregistrée. Vous pourrez la modifier à tout moment.');
    }

    public function remove(Request $request, string $questionCode): RedirectResponse
    {
        [$account] = $this->context($request);
        $answer = $this->profiles->remove($account, $questionCode);
        $this->auditor->record(
            request: $request,
            action: 'advertising.profile.answer_removed',
            outcome: 'success',
            capability: 'advertising.profile.manage.self',
            context: [
                'question_code' => $questionCode,
                'answer_version' => $answer->version,
            ],
        );

        return back()->with('success', 'Cette information facultative n’est plus active dans votre profil intelligent.');
    }

    public function consent(Request $request): RedirectResponse
    {
        [$account] = $this->context($request);
        /** @var array{purpose_code:string,status:string} $validated */
        $validated = $request->validate([
            'purpose_code' => ['required', 'string', 'max:120'],
            'status' => [
                'required',
                Rule::in([
                    AdvertisingConsentStatus::Granted->value,
                    AdvertisingConsentStatus::Denied->value,
                    AdvertisingConsentStatus::Withdrawn->value,
                ]),
            ],
        ]);
        $status = AdvertisingConsentStatus::from($validated['status']);
        $consent = $this->consents->decide(
            account: $account,
            purposeCode: $validated['purpose_code'],
            status: $status,
            channel: 'web',
            context: [
                'trace_id' => $request->attributes->get('trace_id'),
            ],
        );
        $this->auditor->record(
            request: $request,
            action: 'advertising.consent.decided',
            outcome: 'success',
            capability: 'advertising.consent.manage.self',
            context: [
                'purpose_code' => $validated['purpose_code'],
                'status' => $consent->status->value,
                'purpose_version_id' => $consent->advertising_purpose_version_id,
            ],
        );

        return back()->with('success', match ($status) {
            AdvertisingConsentStatus::Granted => 'Votre accord a été enregistré.',
            AdvertisingConsentStatus::Denied => 'Votre refus a été enregistré.',
            AdvertisingConsentStatus::Withdrawn => 'Votre consentement a été retiré pour les nouveaux usages.',
            default => 'Votre décision a été enregistrée.',
        });
    }

    /** @return array{0:Account,1:UserSpace} */
    private function context(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::User, 403);

        return [$account, $space];
    }

    /** @return array<string, mixed> */
    private function baseProps(Account $account, UserSpace $activeSpace): array
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
        ];
    }
}
