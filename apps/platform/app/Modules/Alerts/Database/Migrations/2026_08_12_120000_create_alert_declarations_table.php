<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_declarations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('reviewed_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('category', 32);
            $table->string('situation', 24);
            $table->string('priority', 4)->default('P3');
            $table->string('status', 32)->default('draft');
            $table->string('title', 140);
            $table->text('description');
            $table->text('public_summary')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('area_label', 120)->nullable();
            $table->text('exact_location')->nullable();
            $table->boolean('public_visibility')->default(false);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
            $table->index(['category', 'published_at']);
            $table->index(['city', 'published_at']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_declarations');
    }
};
