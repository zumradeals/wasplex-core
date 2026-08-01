<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('status', 24)->default('active')->index();
            $table->string('password_hash');
            $table->string('locale', 12)->default('fr');
            $table->string('timezone', 64)->default('UTC');
            $table->rememberToken();
            $table->timestampTz('last_authenticated_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('account_identifiers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('normalized_value')->unique();
            $table->string('display_value');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'type']);
        });

        Schema::create('personal_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('avatar_path')->nullable();
            $table->timestampsTz();
        });

        Schema::create('account_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('platform', 80)->nullable();
            $table->string('fingerprint_hash', 64)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'revoked_at']);
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('kind', 32)->default('advertiser');
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        Schema::create('user_spaces', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('label', 120);
            $table->string('status', 24)->default('active');
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampsTz();
            $table->unique(['account_id', 'kind', 'organization_id']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('status', 24)->default('active');
            $table->string('title', 120)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'account_id']);
        });

        Schema::create('space_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('space_id')->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('status', 24)->default('active');
            $table->string('title', 120)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->unique(['space_id', 'account_id']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('invited_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('identifier');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('capability_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('space_id')->nullable()->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('granted_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('capability', 160);
            $table->json('scope')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'capability', 'revoked_at']);
            $table->index(['space_id', 'capability']);
        });

        Schema::create('account_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('device_id')->nullable()->constrained('account_devices')->nullOnDelete();
            $table->foreignUlid('active_space_id')->nullable()->constrained('user_spaces')->nullOnDelete();
            $table->string('session_key_hash', 64)->unique();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('mfa_verified_at')->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'revoked_at']);
        });

        Schema::create('account_mfa_methods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('type', 24)->default('totp');
            $table->text('secret');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'confirmed_at', 'revoked_at']);
        });

        Schema::create('access_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('actor_space_id')->nullable()->constrained('user_spaces')->nullOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUlid('session_id')->nullable()->constrained('account_sessions')->nullOnDelete();
            $table->foreignUlid('device_id')->nullable()->constrained('account_devices')->nullOnDelete();
            $table->string('capability', 160)->nullable();
            $table->string('action', 160);
            $table->string('outcome', 32);
            $table->string('trace_id', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestampTz('occurred_at');
            $table->index(['actor_account_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit_events');
        Schema::dropIfExists('account_mfa_methods');
        Schema::dropIfExists('account_sessions');
        Schema::dropIfExists('capability_grants');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('space_memberships');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('user_spaces');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('account_devices');
        Schema::dropIfExists('personal_profiles');
        Schema::dropIfExists('account_identifiers');
        Schema::dropIfExists('accounts');
    }
};
