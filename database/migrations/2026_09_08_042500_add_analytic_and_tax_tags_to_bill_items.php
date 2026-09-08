<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->json('analytic_distribution')->nullable()->after('expense_account_id');
            $table->json('tax_tag_ids')->nullable()->after('analytic_distribution');
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn(['analytic_distribution', 'tax_tag_ids']);
        });
    }
};
