<?php

use App\Models\Accounting\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->bigInteger('rounding_amount')->default(0)->after('payable_amount');
        });

        $this->ensureAccount(
            '5-2911',
            'Pembulatan Kas',
            Account::TYPE_EXPENSE,
            Account::SUBTYPE_OPERATING_EXPENSE,
            '5-2000',
        );
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn('rounding_amount');
        });
    }

    private function ensureAccount(string $code, string $name, string $type, string $subtype, string $parentCode): void
    {
        $parent = Account::query()->where('code', $parentCode)->first();
        if ($parent === null || Account::query()->where('code', $code)->exists()) {
            return;
        }

        Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'parent_id' => $parent->id,
            'is_active' => true,
            'is_system' => true,
        ]);
    }
};
