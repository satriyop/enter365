<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('session_id')->nullable()->after('external_id')
                ->constrained('bank_reconciliation_sessions')->nullOnDelete();
            $table->smallInteger('match_confidence')->nullable()->after('session_id');
            $table->string('match_rule')->nullable()->after('match_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_id');
            $table->dropColumn(['match_confidence', 'match_rule']);
        });
    }
};
