<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_space_id')->constrained('user_spaces')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['user_space_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_memberships');
    }
};
