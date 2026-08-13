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

    private const VIEW_ROLES = ['organization_admin', 'operations_manager', 'reviewer', 'finance_manager', 'partner_cashier', 'read_only'];

    private const ACT_ROLES = ['organization_admin', 'operations_manager', 'reviewer', 'partner_cashier'];

    public function up(): void
    {
        Schema::create('fund_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_wish_id')->unique();
            $table->ulid('quote_id');
            $table->ulid('provider_organization_id');
            $table->string('status', 32)->default('issued');
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedBigInteger('authorized_reserve_minor')->default(0);
            $table->unsignedBigInteger('ledger_allocated_minor')->default(0);
            $table->unsignedBigInteger('externally_settled_minor')->default(0);
            $table->ulid('ordered_by_account_id');
            $table->timestampTz('ordered_at');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('beneficiary_confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->foreign('fund_wish_id')->references('id')->on('fund_wishes')->cascadeOnDelete();
            $table->foreign('quote_id')->references('id')->on('fund_wish_quotes')->restrictOnDelete();
            $table->foreign('provider_organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('ordered_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['provider_organization_id', 'status']);
        });

        Schema::create('fund_order_milestones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_order_id');
            $table->unsignedInteger('ordinal');
            $table->string('label', 160);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 32)->default('pending');
            $table->boolean('proof_required')->default(true);
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->ulid('submitted_by_account_id')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->ulid('approved_by_account_id')->nullable();
            $table->ulid('ledger_transaction_id')->nullable();
            $table->timestampTz('ledger_posted_at')->nullable();
            $table->string('external_settlement_status', 32)->default('pending');
            $table->string('external_reference', 190)->nullable();
            $table->timestampTz('externally_settled_at')->nullable();
            $table->timestampsTz();

            $table->foreign('fund_order_id')->references('id')->on('fund_orders')->cascadeOnDelete();
            $table->foreign('submitted_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('approved_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->nullOnDelete();
            $table->unique(['fund_order_id', 'ordinal']);
            $table->index(['fund_order_id', 'status']);
        });

        Schema::create('fund_order_proofs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_order_id');
            $table->ulid('fund_order_milestone_id')->nullable();
            $table->ulid('organization_id')->nullable();
            $table->ulid('submitted_by_account_id');
            $table->string('proof_type', 48);
            $table->string('reference', 500)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampsTz();

            $table->foreign('fund_order_id')->references('id')->on('fund_orders')->cascadeOnDelete();
            $table->foreign('fund_order_milestone_id')->references('id')->on('fund_order_milestones')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('submitted_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['fund_order_id', 'proof_type']);
        });

        Schema::create('fund_order_disputes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_order_id');
            $table->ulid('opened_by_account_id');
            $table->string('status', 32)->default('open');
            $table->text('reason');
            $table->string('resolution_code', 48)->nullable();
            $table->text('resolution_note')->nullable();
            $table->ulid('resolved_by_account_id')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->foreign('fund_order_id')->references('id')->on('fund_orders')->cascadeOnDelete();
            $table->foreign('opened_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('resolved_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['fund_order_id', 'status']);
        });

        Schema::create('fund_reserve_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_order_id')->nullable();
            $table->string('direction', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 190);
            $table->ulid('ledger_transaction_id')->nullable();
            $table->ulid('created_by_account_id')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('fund_order_id')->references('id')->on('fund_orders')->nullOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->nullOnDelete();
            $table->foreign('created_by_account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        $assignments = DB::table('professional_role_assignments as roles')
            ->join('professional_spaces as spaces', 'spaces.id', '=', 'roles.professional_space_id')
            ->where('roles.status', 'active')
            ->where('spaces.verification_status', 'verified')
            ->whereIn('spaces.space_kind', self::ELIGIBLE_KINDS)
            ->whereIn('roles.role_code', self::VIEW_ROLES)
            ->get(['roles.account_id', 'roles.role_code', 'roles.assigned_by_account_id', 'spaces.organization_id']);

        foreach ($assignments as $assignment) {
            $this->grant((string) $assignment->account_id, 'professional.funds.order.view', (string) $assignment->organization_id, $assignment->assigned_by_account_id ? (string) $assignment->assigned_by_account_id : null);
            if (in_array((string) $assignment->role_code, self::ACT_ROLES, true)) {
                $this->grant((string) $assignment->account_id, 'professional.funds.milestone.submit', (string) $assignment->organization_id, $assignment->assigned_by_account_id ? (string) $assignment->assigned_by_account_id : null);
                $this->grant((string) $assignment->account_id, 'professional.funds.delivery.confirm', (string) $assignment->organization_id, $assignment->assigned_by_account_id ? (string) $assignment->assigned_by_account_id : null);
            }
        }
    }

    public function down(): void
    {
        DB::table('capability_grants')->whereIn('capability_code', [
            'professional.funds.order.view',
            'professional.funds.milestone.submit',
            'professional.funds.delivery.confirm',
        ])->delete();
        Schema::dropIfExists('fund_reserve_entries');
        Schema::dropIfExists('fund_order_disputes');
        Schema::dropIfExists('fund_order_proofs');
        Schema::dropIfExists('fund_order_milestones');
        Schema::dropIfExists('fund_orders');
    }

    private function grant(string $accountId, string $capability, string $organizationId, ?string $grantedBy): void
    {
        $exists = DB::table('capability_grants')
            ->where('account_id', $accountId)
            ->where('capability_code', $capability)
            ->where('scope_type', 'organization')
            ->where('scope_id', $organizationId)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('capability_grants')->insert([
            'id' => (string) Str::ulid(),
            'account_id' => $accountId,
            'capability_code' => $capability,
            'scope_type' => 'organization',
            'scope_id' => $organizationId,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => null,
            'granted_by' => $grantedBy,
            'revoked_at' => null,
            'revoked_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
