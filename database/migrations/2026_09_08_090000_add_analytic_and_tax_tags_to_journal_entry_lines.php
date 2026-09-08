<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo JE line dimensions (residual after partner_id / #27):
 * - analytic_distribution: JSON map {analytic_account_id: percentage} (no analytic master yet)
 * - tax_tag_ids: JSON int[] of tax-grid / account.tag ids (no tax-tag master yet; VAT stays document-level)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->json('analytic_distribution')->nullable()->after('partner_id');
            $table->json('tax_tag_ids')->nullable()->after('analytic_distribution');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['analytic_distribution', 'tax_tag_ids']);
        });
    }
};
