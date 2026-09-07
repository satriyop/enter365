<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('allow_reconciliation')->default(false)->after('is_system');
            $table->string('currency', 3)->nullable()->after('allow_reconciliation');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['allow_reconciliation', 'currency']);
        });
    }
};
