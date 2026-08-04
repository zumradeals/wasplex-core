<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_purposes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 120)->unique();
            $table->string('domain', 80)->default('advertising');
            $table->string('status', 24)->default('active')->index();
            $table->ulid('current_version_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('advertising_purpose_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_purpose_id')
                ->constrained('advertising_purposes')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 24)->default('draft');
            $table->string('title', 160);
            $table->text('description');
            $table->text('refusal_consequence')->nullable();
            $table->boolean('consent_required')->default(true);
            $table->boolean('requires_new_decision')->default(true);
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->foreignUlid('created_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignUlid('published_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['advertising_purpose_id', 'version']);
        });

        Schema::table('advertising_purposes', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('advertising_purpose_versions')
                ->nullOnDelete();
        });

        Schema::create('advertising_taxonomies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 180)->unique();
            $table->string('category', 80)->index();
            $table->string('label', 160);
            $table->string('value_type', 32)->default('single_choice');
            $table->boolean('allowed_for_targeting')->default(true);
            $table->boolean('sensitive')->default(false);
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('advertising_profile_questions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_taxonomy_id')
                ->constrained('advertising_taxonomies')
                ->restrictOnDelete();
            $table->string('code', 160)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->ulid('current_version_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('advertising_profile_question_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_profile_question_id')
                ->constrained('advertising_profile_questions')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 24)->default('draft');
            $table->text('prompt');
            $table->text('help_text');
            $table->text('privacy_note');
            $table->json('options')->nullable();
            $table->json('purpose_codes');
            $table->boolean('optional')->default(true);
            $table->unsignedInteger('freshness_days')->nullable();
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->foreignUlid('created_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignUlid('published_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['advertising_profile_question_id', 'version']);
        });

        Schema::table('advertising_profile_questions', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('advertising_profile_question_versions')
                ->nullOnDelete();
        });

        Schema::create('advertising_profile_answers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('advertising_profile_question_id')
                ->constrained('advertising_profile_questions')
                ->restrictOnDelete();
            $table->foreignUlid('advertising_taxonomy_id')
                ->constrained('advertising_taxonomies')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('value');
            $table->string('provenance', 48)->default('declared_by_user');
            $table->string('status', 24)->default('active');
            $table->timestampTz('answered_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUlid('replaces_answer_id')->nullable()
                ->constrained('advertising_profile_answers')
                ->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['account_id', 'advertising_profile_question_id', 'version'], 'advertising_answers_version_unique');
            $table->index(['account_id', 'status', 'answered_at'], 'advertising_answers_active_index');
        });

        Schema::create('advertising_consents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('advertising_purpose_id')
                ->constrained('advertising_purposes')
                ->restrictOnDelete();
            $table->foreignUlid('advertising_purpose_version_id')
                ->constrained('advertising_purpose_versions')
                ->restrictOnDelete();
            $table->string('status', 24);
            $table->string('channel', 48)->default('web');
            $table->json('context')->nullable();
            $table->timestampTz('decided_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'advertising_purpose_id', 'decided_at'], 'advertising_consents_history_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE advertising_purpose_versions ADD CONSTRAINT advertising_purpose_version_state_allowed CHECK (state IN ('draft','published','suspended'))");
            DB::statement("ALTER TABLE advertising_profile_question_versions ADD CONSTRAINT advertising_question_version_state_allowed CHECK (state IN ('draft','published','suspended'))");
            DB::statement("ALTER TABLE advertising_profile_answers ADD CONSTRAINT advertising_answer_status_allowed CHECK (status IN ('active','replaced','deleted','contested','expired'))");
            DB::statement("ALTER TABLE advertising_profile_answers ADD CONSTRAINT advertising_answer_provenance_allowed CHECK (provenance IN ('declared_by_user','confirmed_by_user','derived_from_allowed_activity','corrected','contested','expired'))");
            DB::statement("ALTER TABLE advertising_consents ADD CONSTRAINT advertising_consent_status_allowed CHECK (status IN ('granted','denied','withdrawn','expired','superseded'))");
            DB::statement('ALTER TABLE advertising_taxonomies ADD CONSTRAINT advertising_taxonomy_sensitive_targeting_forbidden CHECK (NOT (sensitive AND allowed_for_targeting))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_consents');
        Schema::dropIfExists('advertising_profile_answers');

        Schema::table('advertising_profile_questions', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('advertising_profile_question_versions');
        Schema::dropIfExists('advertising_profile_questions');
        Schema::dropIfExists('advertising_taxonomies');

        Schema::table('advertising_purposes', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('advertising_purpose_versions');
        Schema::dropIfExists('advertising_purposes');
    }
};
