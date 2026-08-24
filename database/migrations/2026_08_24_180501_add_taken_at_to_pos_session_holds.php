<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_session_holds', function (Blueprint $table) {
            $table->timestamp('taken_at')->nullable()->after('lines');
        });
    }

    public function down(): void
    {
        Schema::table('pos_session_holds', function (Blueprint $table) {
            $table->dropColumn('taken_at');
        });
    }
};
