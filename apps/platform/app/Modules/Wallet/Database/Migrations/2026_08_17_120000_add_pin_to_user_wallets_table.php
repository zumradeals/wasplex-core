<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table): void {
            // Hashed with Laravel's Hash facade (bcrypt), never stored or
            // logged in clear text (docs/CLAUDE.md §8, §15). Nullable: a
            // wallet may not have a PIN yet until the member's first
            // transfer prompts creation (P020 §2.2).
            $table->string('pin_hash')->nullable()->after('status');
            $table->timestamp('pin_set_at')->nullable()->after('pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', function (Blueprint $table): void {
            $table->dropColumn(['pin_hash', 'pin_set_at']);
        });
    }
};
