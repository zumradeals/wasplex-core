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
        Schema::create('user_consents', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (docs/CLAUDE.md §6).
            $table->string('account_id');

            $table->foreignUlid('consent_purpose_id')->constrained('consent_purposes')->restrictOnDelete();
            $table->foreignUlid('consent_purpose_version_id')->constrained('consent_purpose_versions')->restrictOnDelete();

            // granted | denied | withdrawn | expired | superseded (docs/17 §16)
            $table->string('status');
            $table->string('channel')->default('web');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'consent_purpose_id']);
        });

        DB::statement("alter table user_consents add constraint user_consents_status_check check (status in ('granted', 'denied', 'withdrawn', 'expired', 'superseded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
