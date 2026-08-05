<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('fingerprint');
            $table->string('user_agent')->nullable();
            $table->string('status')->default('new');
            $table->string('last_ip')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['account_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_devices');
    }
};
