<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Early payment discount
            $table->decimal('early_discount_percent', 5, 2)->default(0)->after('discount_amount');
            $table->integer('early_discount_days')->default(0)->after('early_discount_percent');
            $table->date('early_discount_deadline')->nullable()->after('early_discount_days');
            $table->bigInteger('early_discount_amount')->default(0)->after('early_discount_deadline');

            // Multi-currency support
            $table->string('currency', 3)->default('IDR')->after('total_amount');
            $table->decimal('exchange_rate', 15, 4)->default(1)->after('currency');
            $table->bigInteger('base_currency_total')->default(0)->after('exchange_rate'); // Amount in IDR

            // Recurring invoice reference
            $table->foreignId('recurring_template_id')->nullable()->after('created_by')
                ->constrained('recurring_templates')->nullOnDelete();

            // Reminder tracking
            $table->integer('reminder_count')->default(0)->after('status');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_count');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['recurring_template_id']);
            $table->dropColumn([
                'early_discount_percent',
                'early_discount_days',
                'early_discount_deadline',
                'early_discount_amount',
                'currency',
                'exchange_rate',
                'base_currency_total',
                'recurring_template_id',
                'reminder_count',
                'last_reminder_at',
            ]);
        });
    }
};
