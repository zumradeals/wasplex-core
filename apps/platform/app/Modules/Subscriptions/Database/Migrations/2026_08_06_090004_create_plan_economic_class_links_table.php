<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_economic_class_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_version_id')->constrained('subscription_plan_versions')->cascadeOnDelete();
            $table->foreignUlid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['plan_version_id', 'economic_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_economic_class_links');
    }
};
