<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('device_id')->nullable()->constrained('account_devices')->nullOnDelete();
            $table->string('laravel_session_id')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('mfa_verified_at')->nullable();
            $table->timestamp('last_active_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_sessions');
    }
};
