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
        Schema::create('profile_answers', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (docs/CLAUDE.md §6).
            $table->string('account_id');

            $table->foreignUlid('profile_taxonomy_id')->constrained('profile_taxonomies')->restrictOnDelete();

            // Always 'declared_by_user' in this chantier — no inference
            // engine exists (docs/chantiers/P008-CHANTIER.md §3).
            $table->string('source')->default('declared_by_user');

            $table->timestamp('declared_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'profile_taxonomy_id', 'withdrawn_at']);
        });

        DB::statement("alter table profile_answers add constraint profile_answers_source_check check (source in ('declared_by_user'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_answers');
    }
};
