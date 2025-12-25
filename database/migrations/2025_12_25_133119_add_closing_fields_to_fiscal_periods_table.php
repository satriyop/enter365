<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('is_closed');
            $table->foreignId('closed_by')->nullable()->after('closed_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('closing_entry_id')->nullable()->after('closed_by')
                ->constrained('journal_entries')->nullOnDelete();
            $table->bigInteger('retained_earnings_amount')->nullable()->after('closing_entry_id');
            $table->text('closing_notes')->nullable()->after('retained_earnings_amount');
            $table->boolean('is_locked')->default(false)->after('is_closed'); // Soft lock before full close
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['closing_entry_id']);
            $table->dropColumn([
                'closed_at',
                'closed_by',
                'closing_entry_id',
                'retained_earnings_amount',
                'closing_notes',
                'is_locked',
            ]);
        });
    }
};
