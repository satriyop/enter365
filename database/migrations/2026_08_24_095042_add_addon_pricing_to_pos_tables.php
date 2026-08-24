<?php

use App\Models\Accounting\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->string('pricing_mode', 20)->default('inclusive')->after('qris_account_id');
            $table->decimal('service_rate', 5, 2)->default(0)->after('pricing_mode');
            $table->decimal('tax_add_rate', 5, 2)->default(0)->after('service_rate');
            $table->string('tax_add_name', 32)->default('PBJT')->after('tax_add_rate');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('service_amount')->default(0)->after('subtotal_amount');
            $table->unsignedBigInteger('tax_amount')->default(0)->after('service_amount');
        });

        $this->ensureAccount(
            '4-1005',
            'Pendapatan Service Charge',
            Account::TYPE_REVENUE,
            Account::SUBTYPE_OPERATING_REVENUE,
            '4-1000',
        );
        $this->ensureAccount(
            '2-1210',
            'Utang PBJT',
            Account::TYPE_LIABILITY,
            Account::SUBTYPE_CURRENT_LIABILITY,
            '2-1000',
        );
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'service_rate', 'tax_add_rate', 'tax_add_name']);
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['service_amount', 'tax_amount']);
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
            'opening_balance' => 0,
        ]);
    }
};
