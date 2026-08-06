<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (docs/CLAUDE.md §6). Null for a run
            // triggered by the artisan command outside an HTTP request.
            $table->string('triggered_by')->nullable();

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_checked')->default(0);

            // Count of entries per result_code — a denormalized summary for
            // the admin list view, always derivable from reconciliation_entries.
            $table->jsonb('summary')->default('{}');

            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
