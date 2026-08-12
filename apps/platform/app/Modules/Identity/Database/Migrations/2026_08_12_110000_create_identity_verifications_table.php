<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('reviewed_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('document_type', 32)->nullable();
            $table->string('document_country', 2)->nullable();
            $table->text('document_number')->nullable();
            $table->text('birth_date')->nullable();
            $table->string('document_front_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
