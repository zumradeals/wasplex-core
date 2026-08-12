<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Modules\Funds\Application\Services\FundContributionService;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundPersonalContribution;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminFundsController
{
    public function __construct(private readonly FundContributionService $contributions) {}

    public function dashboard(): JsonResponse
    {
        $wishes = FundWish::query()
            ->with(['category:id,name,icon', 'membership.program:id,name,code', 'personalContributions'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (FundWish $wish): array {
                $contributed = $this->contributions->wishContributionMinor($wish);
                $required = $wish->required_personal_contribution_minor === null ? null : (int) $wish->required_personal_contribution_minor;
                $remaining = $required === null ? null : max(0, $required - $contributed);

                return [
                    ...$wish->toArray(),
                    'personal_contribution_minor' => $contributed,
                    'personal_contribution_remaining_minor' => $remaining,
                    'personal_contribution_progress_percent' => $required === null || $required <= 0
                        ? 0
                        : min(100, (int) floor(($contributed / $required) * 100)),
                ];
            });

        return response()->json([
            'programs' => FundProgram::query()->with(['versions' => fn ($q) => $q->orderByDesc('version')])->orderBy('sort_order')->get(),
            'categories' => FundWishCategory::query()->orderBy('sort_order')->get(),
            'wishes' => $wishes,
            'metrics' => [
                'active_programs' => FundProgram::query()->where('status', FundProgram::STATUS_ACTIVE)->count(),
                'submitted_wishes' => FundWish::query()->where('status', FundWish::STATUS_SUBMITTED)->count(),
                'approved_wishes' => FundWish::query()->where('status', FundWish::STATUS_APPROVED)->count(),
                'personal_contributions_count' => FundPersonalContribution::query()->count(),
                'personal_contributions_minor' => (int) FundPersonalContribution::query()->sum('amount_minor'),
            ],
        ]);
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('fund_programs', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $program = FundProgram::query()->create([
            ...$data,
            'code' => Str::lower($data['code']),
            'status' => FundProgram::STATUS_DRAFT,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        FundAuditEvent::record((string) $request->user()->id, 'FundProgramCreated', 'fund_program', $program->id);

        return response()->json($program, 201);
    }

    public function storeVersion(Request $request, FundProgram $program): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'membership_fee_minor' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'minimum_subscription_age_days' => ['nullable', 'integer', 'min:0'],
            'max_active_wishes' => ['required', 'integer', 'min:1'],
            'max_wishes_per_period' => ['required', 'integer', 'min:1'],
            'max_wish_amount_minor' => ['nullable', 'integer', 'min:1'],
            'personal_contribution_percent' => ['required', 'integer', 'between:0,100'],
            'min_debit_minor' => ['required', 'integer', 'min:0'],
            'max_debit_minor' => ['nullable', 'integer', 'gte:min_debit_minor'],
            'daily_cap_minor' => ['nullable', 'integer', 'min:0'],
            'monthly_cap_minor' => ['nullable', 'integer', 'min:0'],
            'annual_cap_minor' => ['nullable', 'integer', 'min:0'],
            'wasplex_fee_minor' => ['required', 'integer', 'min:0'],
            'notice_hours' => ['required', 'integer', 'min:1'],
            'grace_period_days' => ['required', 'integer', 'min:1'],
            'eligible_subscription_classes' => ['nullable', 'array'],
            'eligible_subscription_classes.*' => ['string', 'max:50'],
        ]);

        $next = ((int) $program->versions()->max('version')) + 1;
        $version = $program->versions()->create([
            ...$data,
            'currency' => Str::upper($data['currency']),
            'version' => $next,
            'minimum_subscription_age_days' => $data['minimum_subscription_age_days'] ?? 0,
            'status' => 'draft',
        ]);

        FundAuditEvent::record((string) $request->user()->id, 'FundProgramVersionCreated', 'fund_program_version', $version->id, ['version' => $next]);

        return response()->json($version, 201);
    }

    public function publishVersion(Request $request, FundProgramVersion $version): JsonResponse
    {
        DB::transaction(function () use ($request, $version): void {
            FundProgramVersion::query()->where('fund_program_id', $version->fund_program_id)->where('id', '!=', $version->id)->where('status', 'published')->update(['status' => 'retired']);
            $version->update(['status' => 'published', 'published_at' => now()]);
            $version->program()->update(['status' => FundProgram::STATUS_ACTIVE]);
            FundAuditEvent::record((string) $request->user()->id, 'FundProgramVersionPublished', 'fund_program_version', $version->id);
        });

        return response()->json($version->refresh());
    }

    public function setProgramStatus(Request $request, FundProgram $program): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([FundProgram::STATUS_ACTIVE, FundProgram::STATUS_DISABLED])]]);
        if ($data['status'] === FundProgram::STATUS_ACTIVE) {
            abort_if($program->publishedVersion() === null, 422, 'Une version publiée est requise avant activation.');
        }
        $program->update(['status' => $data['status']]);
        FundAuditEvent::record((string) $request->user()->id, 'FundProgramStatusChanged', 'fund_program', $program->id, ['status' => $data['status']]);

        return response()->json($program->refresh());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('fund_wish_categories', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_sensitive_documents' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = FundWishCategory::query()->create([
            ...$data,
            'code' => Str::lower($data['code']),
            'is_active' => true,
            'requires_sensitive_documents' => $data['requires_sensitive_documents'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, FundWishCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'requires_sensitive_documents' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $category->update($data);

        return response()->json($category->refresh());
    }

    public function reviewWish(Request $request, FundWish $wish): JsonResponse
    {
        abort_if($wish->status !== FundWish::STATUS_SUBMITTED, 422, 'Seul un vœu soumis peut être revu.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'request_information'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = match ($data['decision']) {
            'approve' => FundWish::STATUS_APPROVED,
            'reject' => FundWish::STATUS_REJECTED,
            default => FundWish::STATUS_NEEDS_INFORMATION,
        };
        if ($status !== FundWish::STATUS_APPROVED) {
            abort_if(trim((string) ($data['note'] ?? '')) === '', 422, 'Une note est requise pour cette décision.');
        }

        $wish->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by_account_id' => $request->user()->id,
            'review_note' => $data['note'] ?? null,
        ]);
        FundAuditEvent::record((string) $request->user()->id, 'FundWishReviewed', 'fund_wish', $wish->id, ['status' => $status]);

        return response()->json($wish->refresh()->load('category:id,name,icon'));
    }
}
