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
        Schema::create('alert_feed_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedSmallInteger('useful_content_interval')->default(5);
            $table->boolean('circles_enabled')->default(true);
            $table->boolean('left_rail_enabled')->default(true);
            $table->boolean('full_screen_enabled')->default(true);
            $table->jsonb('tips')->nullable();
            $table->timestampsTz();
        });

        DB::table('alert_feed_settings')->insert([
            'id' => (string) Str::ulid(),
            'useful_content_interval' => 5,
            'circles_enabled' => true,
            'left_rail_enabled' => true,
            'full_screen_enabled' => true,
            'tips' => json_encode([
                ['title' => 'Protégez vos informations', 'body' => 'Ne publiez jamais vos coordonnées privées dans une alerte publique.'],
                ['title' => 'Un indice peut aider', 'body' => 'Si vous reconnaissez une situation, partagez uniquement une piste vérifiable et utile.'],
                ['title' => 'Urgence réelle ?', 'body' => 'Utilisez SOS uniquement lorsqu’une personne ou un lieu est exposé à un danger immédiat.'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_feed_settings');
    }
};
