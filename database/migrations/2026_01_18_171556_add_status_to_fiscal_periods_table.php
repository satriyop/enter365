<?php

use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->string('status', 20)->default(FiscalPeriodStatus::Open->value)->after('end_date');
        });

        // Migrate existing data: derive status from is_closed and is_locked
        DB::table('fiscal_periods')
            ->where('is_closed', true)
            ->update(['status' => FiscalPeriodStatus::Closed->value]);

        DB::table('fiscal_periods')
            ->where('is_closed', false)
            ->where('is_locked', true)
            ->update(['status' => FiscalPeriodStatus::Locked->value]);

        // Add index for status lookups
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
