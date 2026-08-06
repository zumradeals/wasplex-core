<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table campaigns drop constraint campaigns_status_check');
        DB::statement("alter table campaigns add constraint campaigns_status_check check (status in ('draft', 'quoted', 'funded', 'submitted', 'changes_requested', 'approved', 'rejected', 'suspended'))");
    }

    public function down(): void
    {
        DB::statement('alter table campaigns drop constraint campaigns_status_check');
        DB::statement("alter table campaigns add constraint campaigns_status_check check (status in ('draft', 'quoted', 'funded', 'submitted'))");
    }
};
