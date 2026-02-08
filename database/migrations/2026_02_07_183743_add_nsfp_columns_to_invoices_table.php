<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('nsfp_number', 20)->nullable()->unique()->after('invoice_number');
            $table->foreignId('nsfp_range_id')->after('nsfp_number')->nullable()->constrained('nsfp_ranges')->nullOnDelete();
            $table->dateTime('nsfp_assigned_at')->nullable()->after('nsfp_range_id');
            $table->boolean('is_nsfp_cancelled')->default(false)->after('nsfp_assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['nsfp_range_id']);
            $table->dropColumn(['nsfp_number', 'nsfp_range_id', 'nsfp_assigned_at', 'is_nsfp_cancelled']);
        });
    }
};
