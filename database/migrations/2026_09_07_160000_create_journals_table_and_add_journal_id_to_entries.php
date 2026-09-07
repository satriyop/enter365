<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32); // sales|purchase|bank|cash|miscellaneous
            $table->string('sequence_prefix', 32);
            $table->foreignId('default_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('sequence_prefix');
            $table->index('type');
            $table->index('is_active');
        });

        $now = now();
        DB::table('journals')->insert([
            [
                'name' => 'Sales',
                'type' => 'sales',
                'sequence_prefix' => 'SALE-',
                'default_account_id' => null,
                'currency' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Purchase',
                'type' => 'purchase',
                'sequence_prefix' => 'PURC-',
                'default_account_id' => null,
                'currency' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Bank',
                'type' => 'bank',
                'sequence_prefix' => 'BNK-',
                'default_account_id' => null,
                'currency' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Cash',
                'type' => 'cash',
                'sequence_prefix' => 'CSH-',
                'default_account_id' => null,
                'currency' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Miscellaneous',
                'type' => 'miscellaneous',
                'sequence_prefix' => 'MISC-',
                'default_account_id' => null,
                'currency' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->after('entry_number')->constrained('journals')->nullOnDelete();
            $table->index('journal_id');
        });

        $miscId = DB::table('journals')->where('type', 'miscellaneous')->value('id');
        if ($miscId) {
            DB::table('journal_entries')->whereNull('journal_id')->update(['journal_id' => $miscId]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_id');
        });

        Schema::dropIfExists('journals');
    }
};
