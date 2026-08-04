<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaign_status_allowed');
        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
            ADD CONSTRAINT campaign_status_allowed
            CHECK (status IN (
                'draft',
                'quoted',
                'funded',
                'submitted',
                'changes_requested',
                'approved',
                'rejected',
                'suspended',
                'cancelled'
            ))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE campaign_fundings
            ADD CONSTRAINT campaign_funding_status_allowed
            CHECK (status IN ('reserved', 'submitted', 'approved', 'suspended', 'released'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE campaign_fundings DROP CONSTRAINT IF EXISTS campaign_funding_status_allowed');
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaign_status_allowed');

        DB::statement("UPDATE campaigns SET status = 'submitted' WHERE status IN ('changes_requested', 'approved', 'suspended')");
        DB::statement("UPDATE campaigns SET status = 'cancelled' WHERE status = 'rejected'");
        DB::statement("UPDATE campaign_fundings SET status = 'submitted' WHERE status IN ('approved', 'suspended')");

        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
            ADD CONSTRAINT campaign_status_allowed
            CHECK (status IN ('draft', 'quoted', 'funded', 'submitted', 'cancelled'))
        SQL);
    }
};
