<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make created_by NOT NULL on critical document tables.
     *
     * Ensures audit trail integrity - every document must have a creator.
     */
    public function up(): void
    {
        // Backfill any NULL created_by with the first user
        $adminId = DB::table('users')->orderBy('id')->value('id');

        if ($adminId) {
            $tables = ['invoices', 'bills', 'quotations', 'purchase_orders', 'work_orders'];

            foreach ($tables as $table) {
                if (Schema::hasColumn($table, 'created_by')) {
                    DB::table($table)->whereNull('created_by')->update(['created_by' => $adminId]);
                }
            }
        }

        // Make columns NOT NULL
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->default(1)->change();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->default(1)->change();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->default(1)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->default(1)->change();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->default(1)->change();
        });
    }

    public function down(): void
    {
        $tables = ['invoices', 'bills', 'quotations', 'purchase_orders', 'work_orders'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->default(null)->change();
            });
        }
    }
};
