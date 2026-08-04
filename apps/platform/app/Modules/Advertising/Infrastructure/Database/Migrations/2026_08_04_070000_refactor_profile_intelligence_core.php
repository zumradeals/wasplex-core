<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_markets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('code', 2)->unique();
            $table->string('name', 120);
            $table->char('currency', 3)->default('XOF');
            $table->string('timezone', 80)->default('Africa/Bamako');
            $table->string('default_locale', 12)->default('fr');
            $table->json('supported_locales')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('advertising_sectors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable();
            $table->string('code', 160)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('icon', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('allowed_for_targeting')->default(true);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::table('advertising_sectors', function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('advertising_sectors')
                ->nullOnDelete();
        });

        Schema::create('advertising_sector_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_sector_id')
                ->constrained('advertising_sectors')
                ->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['advertising_sector_id', 'locale'],
                'advertising_sector_translation_unique',
            );
        });

        Schema::table('advertising_taxonomies', function (Blueprint $table): void {
            $table->foreignUlid('advertising_sector_id')
                ->nullable()
                ->after('id')
                ->constrained('advertising_sectors')
                ->restrictOnDelete();
            $table->ulid('parent_id')->nullable()->after('advertising_sector_id');
            $table->string('signal_kind', 48)->default('interest')->after('category')->index();
            $table->json('country_codes')->nullable()->after('signal_kind');
            $table->boolean('user_visible')->default(true)->after('sensitive');
            $table->string('ai_usage_mode', 32)->default('assist_only')->after('user_visible');
            $table->unsignedInteger('default_freshness_days')->nullable()->after('ai_usage_mode');
            $table->unsignedInteger('sort_order')->default(0)->after('default_freshness_days');
        });

        Schema::table('advertising_taxonomies', function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('advertising_taxonomies')
                ->nullOnDelete();
        });

        Schema::create('advertising_profile_signals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('advertising_taxonomy_id')
                ->constrained('advertising_taxonomies')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('value');
            $table->string('source', 48);
            $table->string('status', 24)->default('proposed')->index();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->char('market_code', 2)->nullable()->index();
            $table->text('explanation')->nullable();
            $table->json('evidence')->nullable();
            $table->boolean('requires_user_confirmation')->default(true);
            $table->boolean('created_by_ai')->default(false);
            $table->timestampTz('observed_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->ulid('replaces_signal_id')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['account_id', 'advertising_taxonomy_id', 'version'],
                'advertising_profile_signal_version_unique',
            );
            $table->index(
                ['account_id', 'status', 'expires_at'],
                'advertising_profile_signal_active_index',
            );
        });

        Schema::table('advertising_profile_signals', function (Blueprint $table): void {
            $table->foreign('replaces_signal_id')
                ->references('id')
                ->on('advertising_profile_signals')
                ->nullOnDelete();
        });

        DB::table('advertising_taxonomies')
            ->where('category', 'usage')
            ->update(['signal_kind' => 'habit']);
        DB::table('advertising_taxonomies')
            ->where('category', 'territory')
            ->update(['signal_kind' => 'territory']);
        DB::table('advertising_taxonomies')
            ->where('category', 'project')
            ->update(['signal_kind' => 'project']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE advertising_markets ADD CONSTRAINT advertising_market_status_allowed CHECK (status IN ('draft','active','suspended'))");
            DB::statement("ALTER TABLE advertising_sectors ADD CONSTRAINT advertising_sector_status_allowed CHECK (status IN ('draft','active','suspended','archived'))");
            DB::statement("ALTER TABLE advertising_taxonomies ADD CONSTRAINT advertising_taxonomy_ai_usage_allowed CHECK (ai_usage_mode IN ('forbidden','assist_only','proposal_with_confirmation'))");
            DB::statement("ALTER TABLE advertising_profile_signals ADD CONSTRAINT advertising_profile_signal_status_allowed CHECK (status IN ('proposed','active','rejected','replaced','deleted','expired','contested'))");
            DB::statement("ALTER TABLE advertising_profile_signals ADD CONSTRAINT advertising_profile_signal_source_allowed CHECK (source IN ('declared_by_user','confirmed_by_user','observed_in_wasplex','derived_by_ai','imported_with_consent','corrected_by_user'))");
            DB::statement('ALTER TABLE advertising_profile_signals ADD CONSTRAINT advertising_profile_signal_confidence_range CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_profile_signals');

        Schema::table('advertising_taxonomies', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['advertising_sector_id']);
            $table->dropColumn([
                'advertising_sector_id',
                'parent_id',
                'signal_kind',
                'country_codes',
                'user_visible',
                'ai_usage_mode',
                'default_freshness_days',
                'sort_order',
            ]);
        });

        Schema::dropIfExists('advertising_sector_translations');

        Schema::table('advertising_sectors', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('advertising_sectors');
        Schema::dropIfExists('advertising_markets');
    }
};
