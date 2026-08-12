<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_institutional_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alert_declaration_id')->constrained('alert_declarations')->cascadeOnDelete();
            $table->foreignUlid('professional_space_id')->constrained('professional_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('referred_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('assigned_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('status', 32)->default('referred');
            $table->text('referral_reason')->nullable();
            $table->string('institutional_reference', 120)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampTz('referred_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['alert_declaration_id', 'organization_id']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['alert_declaration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_institutional_cases');
    }
};
