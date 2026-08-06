<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertiser_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Organization (docs/CLAUDE.md §6). Identity already
            // owns name/type/country_code/status for the organization
            // itself — this table only holds what Identity doesn't know:
            // the Studio-specific commercial subtype and verification cycle.
            $table->string('organization_id')->unique();

            // docs/13-studio-annonceur-wasplex.md §9. Nullable: the profile
            // is auto-created empty (draft) the first time the Studio is
            // opened, before the user has chosen a subtype — never
            // defaulted on their behalf.
            $table->string('advertiser_type')->nullable();

            $table->string('legal_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->string('representative_account_id')->nullable();

            // docs/13 §11: draft/pending_verification/verified/active/
            // restricted/suspended/closed.
            $table->string('status')->default('draft');
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_profiles');
    }
};
