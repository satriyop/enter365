<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Early payment discount settings
            $table->decimal('early_discount_percent', 5, 2)->default(0)->after('payment_term_days');
            $table->integer('early_discount_days')->default(0)->after('early_discount_percent');

            // Currency preference
            $table->string('currency', 3)->default('IDR')->after('credit_limit');

            // Bank details for payments
            $table->string('bank_name')->nullable()->after('is_active');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_number');

            // Additional business info
            $table->text('notes')->nullable()->after('bank_account_name');
            $table->date('last_transaction_date')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'early_discount_percent',
                'early_discount_days',
                'currency',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'notes',
                'last_transaction_date',
            ]);
        });
    }
};
