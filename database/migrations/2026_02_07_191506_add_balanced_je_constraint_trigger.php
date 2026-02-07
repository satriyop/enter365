<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * IS-H2: Database-level balanced journal entry constraint.
 *
 * Creates a PostgreSQL CONSTRAINT TRIGGER that fires at COMMIT time (DEFERRABLE INITIALLY DEFERRED)
 * to verify SUM(debit) = SUM(credit) for each journal entry. This is a safety net behind the
 * application-level JournalEntry::isBalanced() check.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_journal_entry_balanced() RETURNS TRIGGER AS $$
            DECLARE
                v_debit  BIGINT;
                v_credit BIGINT;
                v_entry_id BIGINT;
            BEGIN
                v_entry_id := COALESCE(NEW.journal_entry_id, OLD.journal_entry_id);

                SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0)
                  INTO v_debit, v_credit
                  FROM journal_entry_lines
                 WHERE journal_entry_id = v_entry_id;

                -- Allow empty entries (all lines deleted) but reject unbalanced non-zero entries
                IF v_debit != v_credit AND (v_debit + v_credit) > 0 THEN
                    RAISE EXCEPTION 'Journal entry % is not balanced: debit=% credit=%', v_entry_id, v_debit, v_credit;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER trg_journal_entry_balanced
                AFTER INSERT OR UPDATE OR DELETE ON journal_entry_lines
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION check_journal_entry_balanced();
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_journal_entry_balanced ON journal_entry_lines;
            DROP FUNCTION IF EXISTS check_journal_entry_balanced();
        SQL);
    }
};
