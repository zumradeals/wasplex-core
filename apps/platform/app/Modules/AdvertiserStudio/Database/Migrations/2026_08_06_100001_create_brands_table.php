<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/13 §84 — champs exacts, plus les extras nécessaires au
        // laboratoire de marque (§13) que le chantier couvre réellement
        // (slogan, logos) sans construire brand_versions/brand_assets
        // séparées (docs/chantiers/P005-CHANTIER.md §3.2).
        Schema::create('brands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertiser_profile_id')->constrained('advertiser_profiles')->cascadeOnDelete();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('sector')->nullable();
            $table->text('description')->nullable();
            $table->string('slogan')->nullable();

            // brands.status: draft/pending_verification/verified/rejected —
            // distinct from advertiser_profiles.status (§11 is the
            // organization's own cycle, this is the brand's, docs/13 §13).
            $table->string('status')->default('draft');

            $table->string('country_code', 2)->nullable();
            $table->string('website')->nullable();

            // Not foreign keys: a logo is a creative_asset, referenced by
            // value so the media table stays the single owner of files.
            $table->string('primary_logo_asset_id')->nullable();
            $table->string('secondary_logo_asset_id')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['advertiser_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
