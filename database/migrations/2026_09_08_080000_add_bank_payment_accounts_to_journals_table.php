<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('suspense_account_id')
                ->nullable()
                ->after('default_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('outstanding_receipts_account_id')
                ->nullable()
                ->after('suspense_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('outstanding_payments_account_id')
                ->nullable()
                ->after('outstanding_receipts_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('bank_account_number')->nullable()->after('outstanding_payments_account_id');
            $table->boolean('dedicated_payment_sequence')->default(false)->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspense_account_id');
            $table->dropConstrainedForeignId('outstanding_receipts_account_id');
            $table->dropConstrainedForeignId('outstanding_payments_account_id');
            $table->dropColumn(['bank_account_number', 'dedicated_payment_sequence']);
        });
    }
};
