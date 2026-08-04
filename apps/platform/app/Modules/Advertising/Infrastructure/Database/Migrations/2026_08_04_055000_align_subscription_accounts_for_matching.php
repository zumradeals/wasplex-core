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

        DB::statement('ALTER TABLE user_subscriptions ALTER COLUMN account_id TYPE CHAR(26) USING account_id::text');
        DB::statement('ALTER TABLE user_subscriptions ADD CONSTRAINT user_subscriptions_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE user_subscriptions DROP CONSTRAINT IF EXISTS user_subscriptions_account_fk');
        DB::table('user_subscriptions')->delete();
        DB::statement('ALTER TABLE user_subscriptions ALTER COLUMN account_id TYPE UUID USING account_id::uuid');
    }
};
