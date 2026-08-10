<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table profile_taxonomies drop constraint if exists profile_taxonomies_category_check');
        DB::statement("alter table profile_taxonomies add constraint profile_taxonomies_category_check check (category in ('demographic', 'possession', 'usage', 'interest', 'project', 'situation', 'territory'))");
    }

    public function down(): void
    {
        DB::statement('alter table profile_taxonomies drop constraint if exists profile_taxonomies_category_check');
        DB::statement("alter table profile_taxonomies add constraint profile_taxonomies_category_check check (category in ('possession', 'usage', 'interest', 'project', 'situation', 'territory'))");
    }
};
