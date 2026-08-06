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
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('organization_id')->index();
            $table->string('brand_id')->index();
            $table->string('type')->default('fast');
            $table->string('objective_code')->nullable();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('budget_amount_minor')->nullable();
            $table->date('scheduled_start')->nullable();
            $table->date('scheduled_end')->nullable();
            $table->string('created_by');
            $table->timestamps();
        });

        DB::statement("alter table campaigns add constraint campaigns_type_check check (type = 'fast')");
        DB::statement("alter table campaigns add constraint campaigns_status_check check (status in ('draft', 'quoted', 'funded', 'submitted'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
