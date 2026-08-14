<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('card_offer_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('offer_id')->constrained('card_offers')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('supports_virtual')->default(true);
            $table->boolean('supports_physical')->default(false);
            $table->json('services')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['offer_id', 'version']);
        });

        Schema::create('cards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('offer_version_id')->constrained('card_offer_versions')->restrictOnDelete();
            $table->string('public_identifier')->unique();
            $table->string('status')->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('card_qr_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->char('token_hash', 64)->unique();
            $table->string('status')->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'status', 'expires_at']);
        });

        Schema::create('card_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'event_type']);
        });

        $offerId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $now = now();

        DB::table('card_offers')->insert([
            'id' => $offerId,
            'code' => 'WASPLEX_BASE',
            'name' => 'Wasplex Base',
            'description' => 'Carte virtuelle Wasplex de base, liée au compte et à son Wallet.',
            'status' => 'active',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('card_offer_versions')->insert([
            'id' => $versionId,
            'offer_id' => $offerId,
            'version' => 1,
            'status' => 'published',
            'price_minor' => 0,
            'currency' => 'XOF',
            'duration_days' => null,
            'supports_virtual' => true,
            'supports_physical' => false,
            'services' => json_encode(['identity', 'qr_public', 'wallet_entry']),
            'effective_from' => $now,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('card_audit_events');
        Schema::dropIfExists('card_qr_tokens');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('card_offer_versions');
        Schema::dropIfExists('card_offers');
    }
};
