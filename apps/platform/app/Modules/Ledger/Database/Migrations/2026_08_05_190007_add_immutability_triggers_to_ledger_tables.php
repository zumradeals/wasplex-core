<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Docs/CLAUDE.md #7 and docs/chantiers/P002-CHANTIER.md: "protection
 * PostgreSQL des écritures publiées" — application-level immutability
 * (never calling ->update()/->delete() on posted rows) is not sufficient by
 * itself; PostgreSQL must refuse it too. A transaction/its entries/its links
 * remain mutable only while status = 'pending_approval' (the correction
 * propose/approve workflow, docs/chantiers/P002-CHANTIER.md capacités
 * wallet.correction.propose/approve) — once posted, nothing may touch them.
 * ledger_idempotency_keys have no pending state and are immutable from
 * the moment they are inserted.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function ledger_forbid_transaction_mutation()
            returns trigger as $$
            begin
                if TG_OP = 'DELETE' then
                    if OLD.status = 'posted' then
                        raise exception 'ledger_transactions: a posted transaction cannot be deleted (id=%)', OLD.id;
                    end if;
                    return OLD;
                end if;

                if OLD.status = 'posted' then
                    raise exception 'ledger_transactions: a posted transaction cannot be modified (id=%)', OLD.id;
                end if;

                return NEW;
            end;
            $$ language plpgsql;

            create trigger ledger_transactions_forbid_mutation
                before update or delete on ledger_transactions
                for each row execute function ledger_forbid_transaction_mutation();

            create or replace function ledger_forbid_entry_mutation()
            returns trigger as $$
            declare
                parent_status text;
            begin
                select status into parent_status from ledger_transactions where id = OLD.transaction_id;

                if parent_status = 'posted' then
                    raise exception 'ledger_entries: entries of a posted transaction cannot be modified (transaction_id=%)', OLD.transaction_id;
                end if;

                if TG_OP = 'DELETE' then
                    return OLD;
                end if;

                return NEW;
            end;
            $$ language plpgsql;

            create trigger ledger_entries_forbid_mutation
                before update or delete on ledger_entries
                for each row execute function ledger_forbid_entry_mutation();

            create or replace function ledger_forbid_link_mutation()
            returns trigger as $$
            declare
                parent_status text;
            begin
                select status into parent_status from ledger_transactions where id = OLD.transaction_id;

                if parent_status = 'posted' then
                    raise exception 'ledger_transaction_links: links of a posted transaction cannot be modified (transaction_id=%)', OLD.transaction_id;
                end if;

                if TG_OP = 'DELETE' then
                    return OLD;
                end if;

                return NEW;
            end;
            $$ language plpgsql;

            create trigger ledger_transaction_links_forbid_mutation
                before update or delete on ledger_transaction_links
                for each row execute function ledger_forbid_link_mutation();

            create or replace function ledger_forbid_idempotency_key_mutation()
            returns trigger as $$
            begin
                raise exception 'ledger_idempotency_keys: rows are immutable once inserted (idempotency_key=%)', OLD.idempotency_key;
            end;
            $$ language plpgsql;

            create trigger ledger_idempotency_keys_forbid_mutation
                before update or delete on ledger_idempotency_keys
                for each row execute function ledger_forbid_idempotency_key_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop trigger if exists ledger_idempotency_keys_forbid_mutation on ledger_idempotency_keys;
            drop function if exists ledger_forbid_idempotency_key_mutation();

            drop trigger if exists ledger_transaction_links_forbid_mutation on ledger_transaction_links;
            drop function if exists ledger_forbid_link_mutation();

            drop trigger if exists ledger_entries_forbid_mutation on ledger_entries;
            drop function if exists ledger_forbid_entry_mutation();

            drop trigger if exists ledger_transactions_forbid_mutation on ledger_transactions;
            drop function if exists ledger_forbid_transaction_mutation();
        SQL);
    }
};
