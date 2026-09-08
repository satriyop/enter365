<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_company')->default(true);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('job_position')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('country', 2)->nullable();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['is_company', 'parent_id', 'job_position', 'address_line_2', 'country']);
        });
    }
};
