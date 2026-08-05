<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('inviter_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('invited_identifier_type');
            $table->string('invited_identifier_value');
            $table->string('title')->nullable();
            $table->string('token_hash')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->foreignUlid('accepted_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['invited_identifier_type', 'invited_identifier_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
