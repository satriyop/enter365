<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('pph_category', 30)->nullable()->after('base_currency_total');
            $table->decimal('pph_rate', 5, 2)->nullable()->after('pph_category');
            $table->bigInteger('pph_base_amount')->default(0)->after('pph_rate');
            $table->bigInteger('pph_amount')->default(0)->after('pph_base_amount');
            $table->bigInteger('pph_withheld_amount')->default(0)->after('pph_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['pph_category', 'pph_rate', 'pph_base_amount', 'pph_amount', 'pph_withheld_amount']);
        });
    }
};
