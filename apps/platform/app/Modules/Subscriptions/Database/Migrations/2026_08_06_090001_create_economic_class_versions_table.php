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
        Schema::create('economic_class_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();

            // docs/03 §3/§9/§13: quota mensuel, poids dans l'enveloppe
            // utilisateurs, coefficient de ciblage — "ces valeurs ne
            // doivent jamais être codées en dur", donc versionnées ici et
            // jamais lues autrement que via ce catalogue.
            $table->unsignedInteger('quota_monthly');
            $table->decimal('weight_percent', 5, 2);
            $table->decimal('coefficient', 6, 4);

            $table->string('country_code', 2)->nullable();
            $table->string('currency');

            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['economic_class_id', 'effective_from']);
        });

        DB::statement('alter table economic_class_versions add constraint economic_class_versions_quota_positive check (quota_monthly > 0)');
        DB::statement('alter table economic_class_versions add constraint economic_class_versions_weight_range check (weight_percent >= 0 and weight_percent <= 100)');
        DB::statement('alter table economic_class_versions add constraint economic_class_versions_coefficient_positive check (coefficient > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_class_versions');
    }
};
