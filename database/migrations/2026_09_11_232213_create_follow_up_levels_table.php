<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->integer('delay_days');
            $table->unsignedInteger('sequence')->default(10);
            $table->boolean('send_email')->default(true);
            $table->boolean('join_invoices')->default(true);
            $table->text('message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'delay_days']);
        });

        $now = now();
        DB::table('follow_up_levels')->insert([
            [
                'name' => 'Upcoming',
                'delay_days' => -3,
                'sequence' => 10,
                'send_email' => true,
                'join_invoices' => true,
                'message' => 'Invoice jatuh tempo dalam 3 hari.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'First reminder',
                'delay_days' => 1,
                'sequence' => 20,
                'send_email' => true,
                'join_invoices' => true,
                'message' => 'Invoice sudah jatuh tempo.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Second reminder',
                'delay_days' => 7,
                'sequence' => 30,
                'send_email' => true,
                'join_invoices' => true,
                'message' => 'Pengingat kedua untuk invoice jatuh tempo.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Third reminder',
                'delay_days' => 14,
                'sequence' => 40,
                'send_email' => true,
                'join_invoices' => true,
                'message' => 'Pengingat ketiga untuk invoice jatuh tempo.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Final notice',
                'delay_days' => 30,
                'sequence' => 50,
                'send_email' => true,
                'join_invoices' => true,
                'message' => 'Surat peringatan terakhir.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_levels');
    }
};
