<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_spaces', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_space_id')->unique()->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('reviewed_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('space_kind', 48);
            $table->string('verification_status', 32)->default('pending_verification');
            $table->string('legal_name', 180);
            $table->string('trade_name', 180)->nullable();
            $table->text('registration_number')->nullable();
            $table->string('country_code', 2);
            $table->jsonb('territory')->nullable();
            $table->jsonb('purposes')->nullable();
            $table->jsonb('restrictions')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->index(['verification_status', 'space_kind']);
            $table->index(['organization_id', 'verification_status']);
        });

        Schema::create('professional_role_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('professional_space_id')->constrained('professional_spaces')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('role_code', 48);
            $table->string('status', 24)->default('active');
            $table->jsonb('scope')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignUlid('assigned_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['professional_space_id', 'account_id', 'role_code'], 'professional_role_account_unique');
            $table->index(['account_id', 'status']);
        });

        Schema::create('professional_service_points', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('professional_space_id')->constrained('professional_spaces')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('type', 48)->nullable();
            $table->string('country_code', 2);
            $table->string('city', 100)->nullable();
            $table->string('area_label', 160)->nullable();
            $table->text('address')->nullable();
            $table->jsonb('coordinates')->nullable();
            $table->jsonb('opening_hours')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['professional_space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_service_points');
        Schema::dropIfExists('professional_role_assignments');
        Schema::dropIfExists('professional_spaces');
    }
};
