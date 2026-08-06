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
        Schema::create('matching_configurations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('status')->default('draft');

            // Configurable dès ce chantier, non appliqué (docs/chantiers/
            // P008-CHANTIER.md §3.2) : aucun compteur de livraison réelle
            // n'existe avant le Feed (P009).
            $table->unsignedInteger('frequency_window_hours')->default(24);
            $table->unsignedInteger('frequency_max_per_window')->default(3);
            $table->unsignedInteger('fatigue_threshold')->default(10);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table matching_configurations add constraint matching_configurations_status_check check (status in ('draft', 'published'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_configurations');
    }
};
