<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_records', function (Blueprint $table) {
            $table->string('computation', 30)->default('percentage')->after('rate');
            $table->foreignId('invoice_account_id')->nullable()->after('is_active')->constrained('accounts')->nullOnDelete();
            $table->foreignId('refund_account_id')->nullable()->after('invoice_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('tax_tag_id')->nullable()->after('refund_account_id')->constrained('tax_tags')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tax_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_tag_id');
            $table->dropConstrainedForeignId('refund_account_id');
            $table->dropConstrainedForeignId('invoice_account_id');
            $table->dropColumn('computation');
        });
    }
};
