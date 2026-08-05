<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('actor_space_id')->nullable()->constrained('user_spaces')->nullOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('capability_code')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('session_id')->nullable();
            $table->string('device_id')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at');

            $table->index(['actor_account_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_audit_events');
    }
};
