<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_answers', function (Blueprint $table): void {
            $table->boolean('answer_value')->default(true)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('profile_answers', function (Blueprint $table): void {
            $table->dropColumn('answer_value');
        });
    }
};
