<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds tracking for when quotation was sent to customer.
     * Used to determine if Approved quotations should auto-expire:
     * - Sent quotations: Do NOT auto-expire (customer has seen pricing)
     * - Unsent quotations: Auto-expire like Draft/Submitted
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('approved_by');
            $table->foreignId('sent_by')->nullable()->after('sent_at')
                ->constrained('users')->nullOnDelete();
            $table->string('sent_to_email')->nullable()->after('sent_by');
            $table->string('sent_via')->nullable()->after('sent_to_email')
                ->comment('email, print, portal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['sent_by']);
            $table->dropColumn(['sent_at', 'sent_by', 'sent_to_email', 'sent_via']);
        });
    }
};
