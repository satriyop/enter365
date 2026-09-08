<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('profit_account_id')
                ->nullable()
                ->after('outstanding_payments_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('loss_account_id')
                ->nullable()
                ->after('profit_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profit_account_id');
            $table->dropConstrainedForeignId('loss_account_id');
        });
    }
};
