<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_spaces', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('space_type');
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('owner_account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['space_type', 'organization_id']);
            $table->unique(['space_type', 'owner_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_spaces');
    }
};
