<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capability_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('capability_code');
            $table->string('scope_type')->nullable();
            // Not a foreign key: scope_id polymorphically references an
            // organization or a space ULID. A fixed-length ulid() column
            // would space-pad shorter values, so use a plain varchar.
            $table->string('scope_id', 26)->nullable();
            $table->string('status')->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignUlid('granted_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('revoked_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'capability_code', 'status']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_grants');
    }
};
