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
        Schema::table('profile_taxonomies', function (Blueprint $table): void {
            $table->string('facet')->nullable()->after('category');
            $table->string('input_type')->default('boolean')->after('facet');
        });

        DB::statement('update profile_taxonomies set facet = code where facet is null');
        DB::statement("update profile_taxonomies set facet = 'demographic.gender', input_type = 'single_choice' where code like 'demographic.gender.%'");
        DB::statement("update profile_taxonomies set facet = 'usage.primary_network', input_type = 'single_choice' where code like 'usage.reseau_%'");
        DB::statement("update profile_taxonomies set facet = 'territory.approximate_area', input_type = 'single_choice' where code like 'territory.%'");
        DB::statement("update profile_taxonomies set facet = 'interest', input_type = 'multi_choice' where category = 'interest'");
        DB::statement("update profile_taxonomies set facet = 'project', input_type = 'multi_choice' where category = 'project'");
        DB::statement("update profile_taxonomies set facet = 'situation', input_type = 'multi_choice' where category = 'situation'");
        DB::statement("alter table profile_taxonomies add constraint profile_taxonomies_input_type_check check (input_type in ('boolean', 'single_choice', 'multi_choice'))");
    }

    public function down(): void
    {
        DB::statement('alter table profile_taxonomies drop constraint if exists profile_taxonomies_input_type_check');

        Schema::table('profile_taxonomies', function (Blueprint $table): void {
            $table->dropColumn(['facet', 'input_type']);
        });
    }
};
