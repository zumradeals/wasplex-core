<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('economic_class_versions', function (Blueprint $table): void {
            $table->unsignedBigInteger('reward_per_complete_view_minor')->default(30);
        });

        DB::statement(<<<'SQL'
            update economic_class_versions as versions
            set reward_per_complete_view_minor = case classes.code
                when 'FREE' then 30
                when 'PREMIUM' then 40
                when 'GOLD' then 50
                when 'PLATINUM' then 60
                else 30
            end
            from economic_classes as classes
            where classes.id = versions.economic_class_id
        SQL);
    }

    public function down(): void
    {
        Schema::table('economic_class_versions', function (Blueprint $table): void {
            $table->dropColumn('reward_per_complete_view_minor');
        });
    }
};
