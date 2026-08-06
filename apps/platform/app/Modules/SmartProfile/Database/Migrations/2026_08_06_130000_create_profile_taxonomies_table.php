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
        Schema::create('profile_taxonomies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('category');
            $table->string('label');
            $table->string('status')->default('draft');
            $table->unsignedInteger('freshness_days')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table profile_taxonomies add constraint profile_taxonomies_category_check check (category in ('possession', 'usage', 'interest', 'project', 'situation', 'territory'))");
        DB::statement("alter table profile_taxonomies add constraint profile_taxonomies_status_check check (status in ('draft', 'active', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_taxonomies');
    }
};
