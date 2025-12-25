<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable'); // invoices or bills
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('type'); // upcoming, overdue, final_notice
            $table->integer('days_offset'); // Days before(-) or after(+) due date
            $table->date('scheduled_date');
            $table->date('sent_date')->nullable();
            $table->string('status')->default('pending'); // pending, sent, cancelled, failed
            $table->string('channel')->default('email'); // email, sms, whatsapp
            $table->text('message')->nullable();
            $table->json('metadata')->nullable(); // Additional data like email message id
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_date']);
            $table->index('remindable_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
