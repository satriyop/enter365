<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouses', 'is_test')) {
            return;
        }

        DB::table('warehouses')
            ->where(function ($query): void {
                $query->where('code', 'like', 'WH-E2E-%')
                    ->orWhere('code', 'like', 'WH-OP-%')
                    ->orWhere('code', 'like', 'WH-INV-TEST%');
            })
            ->update(['is_test' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('warehouses', 'is_test')) {
            return;
        }

        DB::table('warehouses')
            ->where(function ($query): void {
                $query->where('code', 'like', 'WH-E2E-%')
                    ->orWhere('code', 'like', 'WH-OP-%')
                    ->orWhere('code', 'like', 'WH-INV-TEST%');
            })
            ->update(['is_test' => false]);
    }
};
