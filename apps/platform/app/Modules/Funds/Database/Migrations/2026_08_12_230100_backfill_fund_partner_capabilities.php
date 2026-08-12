<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const ELIGIBLE_KINDS = ['partner', 'merchant', 'service_provider', 'healthcare_institution', 'financial_operator'];

    private const VIEW_ROLES = ['organization_admin', 'operations_manager', 'reviewer', 'finance_manager', 'partner_cashier', 'read_only'];

    private const RESPOND_ROLES = ['organization_admin', 'operations_manager', 'reviewer', 'partner_cashier'];

    public function up(): void
    {
        $assignments = DB::table('professional_role_assignments as roles')
            ->join('professional_spaces as spaces', 'spaces.id', '=', 'roles.professional_space_id')
            ->where('roles.status', 'active')
            ->where('spaces.verification_status', 'verified')
            ->whereIn('spaces.space_kind', self::ELIGIBLE_KINDS)
            ->whereIn('roles.role_code', self::VIEW_ROLES)
            ->get(['roles.account_id', 'roles.role_code', 'roles.assigned_by_account_id', 'spaces.organization_id']);

        foreach ($assignments as $assignment) {
            $this->grant(
                (string) $assignment->account_id,
                'professional.funds.quote.view',
                (string) $assignment->organization_id,
                $assignment->assigned_by_account_id ? (string) $assignment->assigned_by_account_id : null,
            );

            if (in_array((string) $assignment->role_code, self::RESPOND_ROLES, true)) {
                $this->grant(
                    (string) $assignment->account_id,
                    'professional.funds.quote.respond',
                    (string) $assignment->organization_id,
                    $assignment->assigned_by_account_id ? (string) $assignment->assigned_by_account_id : null,
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('capability_grants')
            ->whereIn('capability_code', ['professional.funds.quote.view', 'professional.funds.quote.respond'])
            ->delete();
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
