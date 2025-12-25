<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable'); // invoices, bills, payments, journal_entries, etc.
            $table->string('filename'); // Original filename
            $table->string('disk')->default('local'); // Storage disk
            $table->string('path'); // Storage path
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->string('description')->nullable();
            $table->string('category')->nullable(); // invoice_pdf, receipt, contract, etc.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
