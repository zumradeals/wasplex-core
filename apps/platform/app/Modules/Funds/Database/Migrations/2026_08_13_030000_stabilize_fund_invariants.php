<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE fund_wish_queue_entries SET priority_score = 0 WHERE lane = 'ordinary'");

        DB::unprepared(<<<'SQL'
CREATE INDEX IF NOT EXISTS fund_queue_fifo_idx
    ON fund_wish_queue_entries (fund_program_id, status, lane, queued_at, id);

CREATE UNIQUE INDEX IF NOT EXISTS fund_rehabilitation_one_active_per_membership
    ON fund_rehabilitation_cases (fund_membership_id)
    WHERE status IN ('required', 'in_progress');

CREATE UNIQUE INDEX IF NOT EXISTS fund_collection_fee_once_per_wish_participant
    ON fund_collection_participants (snapshot_id, account_id)
    WHERE fee_paid_minor > 0;

ALTER TABLE fund_wish_queue_entries
    DROP CONSTRAINT IF EXISTS fund_queue_ordinary_priority_zero;
ALTER TABLE fund_wish_queue_entries
    ADD CONSTRAINT fund_queue_ordinary_priority_zero
    CHECK (lane <> 'ordinary' OR priority_score = 0);

ALTER TABLE fund_reserve_allocations
    DROP CONSTRAINT IF EXISTS fund_reserve_consumption_within_authorization;
ALTER TABLE fund_reserve_allocations
    ADD CONSTRAINT fund_reserve_consumption_within_authorization
    CHECK (consumed_minor <= amount_minor);

ALTER TABLE fund_collection_participants
    DROP CONSTRAINT IF EXISTS fund_collection_participant_paid_within_due;
ALTER TABLE fund_collection_participants
    ADD CONSTRAINT fund_collection_participant_paid_within_due
    CHECK (solidarity_paid_minor <= solidarity_due_minor AND fee_paid_minor <= fee_due_minor);

ALTER TABLE fund_collection_debits
    DROP CONSTRAINT IF EXISTS fund_collection_positive_debit_requires_ledger;
ALTER TABLE fund_collection_debits
    ADD CONSTRAINT fund_collection_positive_debit_requires_ledger
    CHECK ((debited_solidarity_minor + debited_fee_minor) = 0 OR ledger_transaction_id IS NOT NULL);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fund_collection_debits DROP CONSTRAINT IF EXISTS fund_collection_positive_debit_requires_ledger;
ALTER TABLE fund_collection_participants DROP CONSTRAINT IF EXISTS fund_collection_participant_paid_within_due;
ALTER TABLE fund_reserve_allocations DROP CONSTRAINT IF EXISTS fund_reserve_consumption_within_authorization;
ALTER TABLE fund_wish_queue_entries DROP CONSTRAINT IF EXISTS fund_queue_ordinary_priority_zero;
DROP INDEX IF EXISTS fund_collection_fee_once_per_wish_participant;
DROP INDEX IF EXISTS fund_rehabilitation_one_active_per_membership;
DROP INDEX IF EXISTS fund_queue_fifo_idx;
SQL);
    }
};
