<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const ELIGIBLE_KINDS = ['partner', 'merchant', 'service_provider', 'healthcare_institution', 'financial_operator'];

    private const ACT_ROLES = ['organization_admin', 'operations_manager', 'reviewer', 'partner_cashier'];

    public function up(): void
    {
        Schema::create('fund_order_warranties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_order_id')->unique();
            $table->ulid('provider_organization_id');
            $table->string('status', 32)->default('active');
            $table->string('reference', 190)->nullable();
            $table->text('terms')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->ulid('registered_by_account_id');
            $table->timestampsTz();

            $table->foreign('fund_order_id')->references('id')->on('fund_orders')->cascadeOnDelete();
            $table->foreign('provider_organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('registered_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['provider_organization_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('fund_warranty_claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_order_warranty_id');
            $table->ulid('opened_by_account_id');
            $table->string('status', 32)->default('open');
            $table->text('issue');
            $table->text('provider_response')->nullable();
            $table->ulid('responded_by_account_id')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->string('resolution_code', 48)->nullable();
            $table->text('resolution_note')->nullable();
            $table->ulid('resolved_by_account_id')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->foreign('fund_order_warranty_id')->references('id')->on('fund_order_warranties')->cascadeOnDelete();
            $table->foreign('opened_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('responded_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('resolved_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['fund_order_warranty_id', 'status']);
        });

        $assignments = DB::table('professional_role_assignments as roles')
            ->join('professional_spaces as spaces', 'spaces.id', '=', 'roles.professional_space_id')
            ->where('roles.status', 'active')
            ->where('spaces.verification_status', 'verified')
            ->whereIn('spaces.space_kind', self::ELIGIBLE_KINDS)
            ->whereIn('roles.role_code', self::ACT_ROLES)
            ->get(['roles.account_id', 'roles.assigned_by_account_id', 'spaces.organization_id']);

        foreach ($assignments as $assignment) {
            $exists = DB::table('capability_grants')
                ->where('account_id', $assignment->account_id)
                ->where('capability_code', 'professional.funds.warranty.manage')
                ->where('scope_type', 'organization')
                ->where('scope_id', $assignment->organization_id)
                ->where('status', 'active')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('capability_grants')->insert([
                'id' => (string) Str::ulid(),
                'account_id' => $assignment->account_id,
                'capability_code' => 'professional.funds.warranty.manage',
                'scope_type' => 'organization',
                'scope_id' => $assignment->organization_id,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => null,
                'granted_by' => $assignment->assigned_by_account_id,
                'revoked_at' => null,
                'revoked_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('capability_grants')
            ->where('capability_code', 'professional.funds.warranty.manage')
            ->delete();

        Schema::dropIfExists('fund_warranty_claims');
        Schema::dropIfExists('fund_order_warranties');
    }
};
