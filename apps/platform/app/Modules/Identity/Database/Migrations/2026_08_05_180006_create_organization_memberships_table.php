<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('joined_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
