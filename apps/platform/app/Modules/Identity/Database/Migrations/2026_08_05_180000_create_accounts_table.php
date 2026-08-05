<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('status')->default('pending_verification');
            $table->string('password');
            $table->string('country_code', 2);
            $table->string('language', 5)->default('fr');
            $table->string('timezone')->default('UTC');
            $table->string('totp_secret')->nullable();
            $table->timestamp('totp_enabled_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('restricted_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
