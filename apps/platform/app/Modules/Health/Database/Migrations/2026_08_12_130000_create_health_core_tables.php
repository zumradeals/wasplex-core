<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_patients', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('account_id')->nullable()->unique();
            $table->string('status')->default('active');
            $table->string('territory', 2)->nullable();
            $table->string('origin')->default('self');
            $table->string('verification_level')->default('self_declared');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::create('health_emergency_capsules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('patient_id')->unique();
            $table->text('payload')->nullable();
            $table->string('provenance')->default('self_declared');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('health_patients')->cascadeOnDelete();
        });

        Schema::create('health_consents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('patient_id');
            $table->string('purpose');
            $table->boolean('granted')->default(false);
            $table->string('version')->default('v1');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'purpose']);
            $table->foreign('patient_id')->references('id')->on('health_patients')->cascadeOnDelete();
        });

        Schema::create('health_representatives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('patient_id');
            $table->text('representative_name');
            $table->text('representative_phone')->nullable();
            $table->string('relationship');
            $table->string('scope')->default('emergency_contact');
            $table->string('status')->default('pending');
            $table->string('proof_status')->default('unverified');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('health_patients')->cascadeOnDelete();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('health_access_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('patient_id');
            $table->ulid('actor_account_id')->nullable();
            $table->string('actor_type')->default('account');
            $table->string('access_type');
            $table->string('purpose');
            $table->string('institution_name')->nullable();
            $table->text('justification')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('health_patients')->cascadeOnDelete();
            $table->foreign('actor_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['patient_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_access_events');
        Schema::dropIfExists('health_representatives');
        Schema::dropIfExists('health_consents');
        Schema::dropIfExists('health_emergency_capsules');
        Schema::dropIfExists('health_patients');
    }
};
