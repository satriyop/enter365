<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS pos_sessions_one_open_per_user ON pos_sessions (opened_by) WHERE status = 'open'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS pos_sessions_one_open_per_user ON pos_sessions (opened_by) WHERE status = 'open'");
        }
    }

    public function down(): void
    {
        Schema::getConnection()->getSchemaBuilder();
        DB::statement('DROP INDEX IF EXISTS pos_sessions_one_open_per_user');
    }
};
