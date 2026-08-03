<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $actorColumns = [
        'created_by_account_id',
        'approved_by_account_id',
        'published_by_account_id',
        'suspended_by_account_id',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->actorColumns as $column) {
            DB::statement(
                "ALTER TABLE economic_class_versions ALTER COLUMN {$column} TYPE CHAR(26) USING {$column}::text",
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('economic_class_versions')->update([
            'created_by_account_id' => null,
            'approved_by_account_id' => null,
            'published_by_account_id' => null,
            'suspended_by_account_id' => null,
        ]);

        foreach ($this->actorColumns as $column) {
            DB::statement(
                "ALTER TABLE economic_class_versions ALTER COLUMN {$column} TYPE UUID USING {$column}::uuid",
            );
        }
    }
};
