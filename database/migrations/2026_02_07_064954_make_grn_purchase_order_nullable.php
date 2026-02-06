<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreignId('contact_id')->nullable()->after('warehouse_id')->constrained('contacts')->nullOnDelete();
        });

        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->foreignId('purchase_order_id')->nullable(false)->change();
        });

        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable(false)->change();
        });
    }
};
