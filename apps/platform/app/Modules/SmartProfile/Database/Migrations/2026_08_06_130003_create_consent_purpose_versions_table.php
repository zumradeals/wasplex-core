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
        Schema::create('consent_purpose_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('consent_purpose_id')->constrained('consent_purposes')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('name');
            $table->text('description');
            $table->boolean('requires_new_decision')->default(false);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['consent_purpose_id', 'version_number']);
        });

        DB::statement("alter table consent_purpose_versions add constraint consent_purpose_versions_status_check check (status in ('draft', 'published'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_purpose_versions');
    }
};
