<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertiser_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertiser_space_id')->unique()->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('updated_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('display_name', 160);
            $table->string('legal_name', 200)->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->char('country_code', 2)->default('CI');
            $table->string('city', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertiser_space_id')->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->string('status', 32)->default('active');
            $table->ulid('active_version_id')->nullable();
            $table->timestamps();

            $table->unique(['advertiser_space_id', 'slug']);
            $table->index(['advertiser_space_id', 'status']);
        });

        Schema::create('creative_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertiser_space_id')->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignUlid('uploaded_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('status', 32)->default('ready');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk', 64);
            $table->string('path', 700);
            $table->char('checksum_sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path']);
            $table->index(['advertiser_space_id', 'kind', 'status']);
        });

        Schema::create('brand_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('public_name', 160);
            $table->string('slogan', 220)->nullable();
            $table->text('description')->nullable();
            $table->char('primary_color', 7)->default('#0EA5E9');
            $table->char('secondary_color', 7)->default('#F97316');
            $table->foreignUlid('logo_asset_id')->nullable()->constrained('creative_assets')->nullOnDelete();
            $table->string('state', 32)->default('published');
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('published_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'version']);
            $table->index(['brand_id', 'state']);
        });

        Schema::create('brand_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignUlid('creative_asset_id')->constrained('creative_assets')->cascadeOnDelete();
            $table->string('role', 40)->default('creative');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['brand_id', 'creative_asset_id', 'role'], 'brand_asset_role_unique');
        });

        Schema::create('creative_asset_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('creative_asset_id')->constrained('creative_assets')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('label', 180)->nullable();
            $table->string('alt_text', 300)->nullable();
            $table->string('usage_context', 80)->nullable();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['creative_asset_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_asset_versions');
        Schema::dropIfExists('brand_assets');
        Schema::dropIfExists('brand_versions');
        Schema::dropIfExists('creative_assets');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('advertiser_profiles');
    }
};
