<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_price_catalogs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->string('name', 160);
            $table->string('unit', 16)->default('WP');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedBigInteger('cpm_minor');
            $table->unsignedBigInteger('minimum_budget_minor');
            $table->unsignedInteger('platform_share_basis_points')->default(5000);
            $table->unsignedInteger('user_share_basis_points')->default(5000);
            $table->string('status', 24)->default('published');
            $table->ulid('created_by_account_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('advertiser_space_id');
            $table->ulid('organization_id');
            $table->ulid('brand_id');
            $table->ulid('created_by_account_id');
            $table->string('name', 180);
            $table->string('status', 24)->default('draft');
            $table->ulid('active_version_id')->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('advertiser_space_id')->references('id')->on('user_spaces')->restrictOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('brand_id')->references('id')->on('brands')->restrictOnDelete();
            $table->foreign('created_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['advertiser_space_id', 'status']);
        });

        Schema::create('campaign_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->unsignedInteger('version');
            $table->string('objective', 80);
            $table->string('headline', 180)->nullable();
            $table->text('body')->nullable();
            $table->string('call_to_action', 80)->nullable();
            $table->text('destination_url')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('territory_name', 160)->nullable();
            $table->unsignedInteger('radius_km')->nullable();
            $table->json('selected_classes');
            $table->unsignedBigInteger('requested_budget_minor');
            $table->ulid('created_by_account_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('created_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->unique(['campaign_id', 'version']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('campaign_versions')->nullOnDelete();
        });

        Schema::create('campaign_creatives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('campaign_version_id');
            $table->ulid('creative_asset_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('campaign_version_id')->references('id')->on('campaign_versions')->cascadeOnDelete();
            $table->foreign('creative_asset_id')->references('id')->on('creative_assets')->restrictOnDelete();
            $table->unique(['campaign_version_id', 'creative_asset_id']);
        });

        Schema::create('campaign_audiences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('campaign_version_id');
            $table->unsignedInteger('version');
            $table->string('estimate_kind', 40)->default('planning');
            $table->string('territory_name', 160);
            $table->unsignedInteger('radius_km');
            $table->json('selected_classes');
            $table->unsignedBigInteger('estimated_min');
            $table->unsignedBigInteger('estimated_max');
            $table->json('assumptions')->nullable();
            $table->timestamp('estimated_at');
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('campaign_version_id')->references('id')->on('campaign_versions')->cascadeOnDelete();
            $table->unique(['campaign_id', 'version']);
        });

        Schema::create('campaign_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('campaign_version_id');
            $table->ulid('audience_id');
            $table->ulid('price_catalog_id');
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('issued');
            $table->string('unit', 16)->default('WP');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('platform_share_minor');
            $table->unsignedBigInteger('user_share_minor');
            $table->unsignedBigInteger('cpm_minor');
            $table->unsignedBigInteger('estimated_impressions');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('funded_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('campaign_version_id')->references('id')->on('campaign_versions')->restrictOnDelete();
            $table->foreign('audience_id')->references('id')->on('campaign_audiences')->restrictOnDelete();
            $table->foreign('price_catalog_id')->references('id')->on('campaign_price_catalogs')->restrictOnDelete();
            $table->unique(['campaign_id', 'version']);
        });

        Schema::create('campaign_fundings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('quote_id')->unique();
            $table->ulid('wallet_id');
            $table->unsignedBigInteger('amount_minor');
            $table->string('unit', 16);
            $table->char('currency', 3);
            $table->string('status', 24)->default('reserved');
            $table->timestamp('reserved_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('quote_id')->references('id')->on('campaign_quotes')->restrictOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->restrictOnDelete();
        });

        Schema::create('campaign_budget_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_funding_id')->unique();
            $table->ulid('wallet_reservation_id')->unique();
            $table->string('business_reference', 190)->unique();
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_funding_id')->references('id')->on('campaign_fundings')->cascadeOnDelete();
            $table->foreign('wallet_reservation_id')->references('id')->on('wallet_reservations')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE campaign_price_catalogs ADD CONSTRAINT campaign_price_shares_total CHECK (platform_share_basis_points + user_share_basis_points = 10000)');
            DB::statement('ALTER TABLE campaign_versions ADD CONSTRAINT campaign_dates_ordered CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)');
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaign_status_allowed CHECK (status IN ('draft','quoted','funded','submitted','cancelled'))");
            DB::statement("ALTER TABLE campaign_quotes ADD CONSTRAINT campaign_quote_status_allowed CHECK (status IN ('issued','funded','void'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_budget_reservations');
        Schema::dropIfExists('campaign_fundings');
        Schema::dropIfExists('campaign_quotes');
        Schema::dropIfExists('campaign_audiences');
        Schema::dropIfExists('campaign_creatives');
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });
        Schema::dropIfExists('campaign_versions');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('campaign_price_catalogs');
    }
};
