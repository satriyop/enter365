<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            ALTER TABLE journal_entry_lines
            ADD CONSTRAINT journal_entry_lines_signed_exclusive
            CHECK (debit >= 0 AND credit >= 0 AND (debit = 0 OR credit = 0))
        ');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE journal_entry_lines DROP CONSTRAINT IF EXISTS journal_entry_lines_signed_exclusive');
    }
};
