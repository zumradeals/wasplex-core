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
        Schema::create('consent_purposes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        DB::statement("alter table consent_purposes add constraint consent_purposes_status_check check (status in ('draft', 'active', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_purposes');
    }
};
