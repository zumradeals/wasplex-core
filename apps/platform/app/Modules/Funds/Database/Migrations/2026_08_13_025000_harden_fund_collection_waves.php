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
        Schema::table('fund_collection_participants', function (Blueprint $table): void {
            $table->unsignedInteger('wave_number')->default(1)->after('snapshot_id');
            $table->unsignedBigInteger('wave_target_minor')->nullable()->after('wave_number');
            $table->char('wave_hash', 64)->nullable()->after('wave_target_minor');
            $table->timestampTz('wave_notice_at')->nullable()->after('wave_hash');
            $table->timestampTz('wave_scheduled_at')->nullable()->after('wave_notice_at');
            $table->string('wave_status', 32)->default('scheduled')->after('wave_scheduled_at');
        });

        DB::statement(<<<'SQL'
UPDATE fund_collection_participants AS participant
SET wave_number = 1,
    wave_target_minor = snapshot.collective_amount_minor,
    wave_hash = snapshot.snapshot_hash,
    wave_notice_at = snapshot.notice_at,
    wave_scheduled_at = snapshot.scheduled_at,
    wave_status = snapshot.status
FROM fund_collection_snapshots AS snapshot
WHERE snapshot.id = participant.snapshot_id
SQL);

        Schema::table('fund_collection_participants', function (Blueprint $table): void {
            $table->dropUnique('fund_collection_participants_snapshot_id_account_id_unique');
            $table->unique(
                ['snapshot_id', 'wave_number', 'account_id'],
                'fund_collection_participant_wave_account_unique',
            );
            $table->index(
                ['snapshot_id', 'wave_number', 'wave_status'],
                'fund_collection_participant_wave_status_idx',
            );
            $table->index('wave_hash', 'fund_collection_participant_wave_hash_idx');
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION wasplex_enforce_fund_collection_target()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    target_minor bigint;
    already_debited_minor bigint;
BEGIN
    IF NEW.debited_solidarity_minor IS NULL OR NEW.debited_solidarity_minor <= 0 THEN
        RETURN NEW;
    END IF;

    SELECT collective_amount_minor
      INTO target_minor
      FROM fund_collection_snapshots
     WHERE id = NEW.snapshot_id
     FOR UPDATE;

    SELECT COALESCE(SUM(debited_solidarity_minor), 0)
      INTO already_debited_minor
      FROM fund_collection_debits
     WHERE snapshot_id = NEW.snapshot_id;

    IF already_debited_minor + NEW.debited_solidarity_minor > target_minor THEN
        RAISE EXCEPTION 'fund_collection_overcollection_blocked snapshot=% target=% already=% requested=%',
            NEW.snapshot_id,
            target_minor,
            already_debited_minor,
            NEW.debited_solidarity_minor;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS fund_collection_target_guard ON fund_collection_debits;

CREATE TRIGGER fund_collection_target_guard
BEFORE INSERT ON fund_collection_debits
FOR EACH ROW
EXECUTE FUNCTION wasplex_enforce_fund_collection_target();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS fund_collection_target_guard ON fund_collection_debits;
DROP FUNCTION IF EXISTS wasplex_enforce_fund_collection_target();
SQL);

        Schema::table('fund_collection_participants', function (Blueprint $table): void {
            $table->dropUnique('fund_collection_participant_wave_account_unique');
            $table->dropIndex('fund_collection_participant_wave_status_idx');
            $table->dropIndex('fund_collection_participant_wave_hash_idx');
        });

        DB::table('fund_collection_participants')->where('wave_number', '>', 1)->delete();

        Schema::table('fund_collection_participants', function (Blueprint $table): void {
            $table->unique(['snapshot_id', 'account_id']);
            $table->dropColumn([
                'wave_number',
                'wave_target_minor',
                'wave_hash',
                'wave_notice_at',
                'wave_scheduled_at',
                'wave_status',
            ]);
        });
    }
};
