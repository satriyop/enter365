<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->string('landed_cost_number')->unique();
            $table->foreignId('goods_receipt_note_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('cost_type'); // shipping, insurance, customs, other
            $table->bigInteger('total_amount');
            $table->string('allocation_method')->default('by_value'); // by_value, by_quantity
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft, applied, reversed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('goods_receipt_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_costs');
    }
};
