<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_quote_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_wish_id');
            $table->ulid('professional_space_id');
            $table->ulid('organization_id');
            $table->ulid('requested_by_account_id');
            $table->string('status', 32)->default('requested');
            $table->string('request_summary', 500)->nullable();
            $table->jsonb('requirements')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->foreign('fund_wish_id')->references('id')->on('fund_wishes')->cascadeOnDelete();
            $table->foreign('professional_space_id')->references('id')->on('professional_spaces')->restrictOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('requested_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->unique(['fund_wish_id', 'organization_id'], 'fund_quote_request_wish_org_unique');
            $table->index(['organization_id', 'status']);
            $table->index(['fund_wish_id', 'status']);
        });

        Schema::create('fund_wish_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('quote_request_id');
            $table->ulid('fund_wish_id');
            $table->ulid('organization_id');
            $table->ulid('submitted_by_account_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->timestampTz('submitted_at');
            $table->ulid('selected_by_account_id')->nullable();
            $table->timestampTz('selected_at')->nullable();
            $table->timestampsTz();

            $table->foreign('quote_request_id')->references('id')->on('fund_quote_requests')->cascadeOnDelete();
            $table->foreign('fund_wish_id')->references('id')->on('fund_wishes')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('submitted_by_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('selected_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->unique('quote_request_id');
            $table->index(['fund_wish_id', 'status']);
        });

        Schema::table('fund_wishes', function (Blueprint $table): void {
            $table->ulid('selected_quote_id')->nullable();
            $table->ulid('provider_organization_id')->nullable();
            $table->unsignedBigInteger('validated_amount_minor')->nullable();
            $table->timestampTz('cost_validated_at')->nullable();

            $table->foreign('selected_quote_id')->references('id')->on('fund_wish_quotes')->nullOnDelete();
            $table->foreign('provider_organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fund_wishes', function (Blueprint $table): void {
            $table->dropForeign(['selected_quote_id']);
            $table->dropForeign(['provider_organization_id']);
            $table->dropColumn(['selected_quote_id', 'provider_organization_id', 'validated_amount_minor', 'cost_validated_at']);
        });

        Schema::dropIfExists('fund_wish_quotes');
        Schema::dropIfExists('fund_quote_requests');
    }
};
