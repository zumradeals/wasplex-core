<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('advertising_price_versions')->where('status', 'published')->exists()) {
            return;
        }

        $draft = DB::table('advertising_price_versions')
            ->where('status', 'draft')
            ->orderByDesc('created_at')
            ->first();

        if ($draft !== null) {
            DB::table('advertising_price_versions')->where('id', $draft->id)->update([
                'status' => 'published',
                'effective_from' => now(),
                'published_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
