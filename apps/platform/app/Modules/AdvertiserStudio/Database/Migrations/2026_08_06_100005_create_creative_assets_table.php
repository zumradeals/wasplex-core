<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/13 §19 — métadonnées exactes ; §20 pour les statuts.
        Schema::create('creative_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained('brands')->cascadeOnDelete();

            // image | video | logo | text | document.
            $table->string('type');
            $table->string('filename');
            $table->string('format');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('language')->nullable();

            // owned | licensed | unknown (§19 "rights_status").
            $table->string('rights_status')->default('unknown');

            // uploading -> ready | needs_changes | rejected, puis
            // approved/archived décidés en modération (§20). Sans pipeline
            // asynchrone réel, la validation technique est synchrone
            // (docs/chantiers/P005-CHANTIER.md §3.3) : jamais "processing"
            // en pratique dans ce chantier, la valeur reste dans l'énum
            // pour rester fidèle au vocabulaire officiel.
            $table->string('moderation_status')->default('uploading');

            $table->string('storage_disk')->default('public');
            $table->string('storage_path');

            // Not a foreign key: cross-module reference by value to
            // Identity's Account.
            $table->string('created_by');

            $table->timestamps();

            $table->index(['brand_id', 'moderation_status']);
        });

        DB::statement('alter table creative_assets add constraint creative_assets_size_positive check (size > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_assets');
    }
};
