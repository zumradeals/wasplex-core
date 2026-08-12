<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_professional_access_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained('health_patients')->cascadeOnDelete();
            $table->foreignUlid('professional_space_id')->constrained('professional_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('requester_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('decided_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('purpose', 48);
            $table->string('status', 24)->default('pending');
            $table->text('justification');
            $table->timestampTz('requested_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();

            $table->index(['patient_id', 'status', 'requested_at']);
            $table->index(['organization_id', 'status', 'requested_at']);
        });

        Schema::table('health_access_events', function (Blueprint $table): void {
            $table->foreignUlid('professional_space_id')->nullable()->after('actor_account_id')->constrained('professional_spaces')->nullOnDelete();
            $table->foreignUlid('organization_id')->nullable()->after('professional_space_id')->constrained('organizations')->nullOnDelete();
            $table->string('consent_basis', 48)->nullable()->after('purpose');
            $table->index(['organization_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('health_access_events', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'accessed_at']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('professional_space_id');
            $table->dropColumn('consent_basis');
        });

        Schema::dropIfExists('health_professional_access_requests');
    }
};
