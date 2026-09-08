<?php

use App\Models\Accounting\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Account::query()->where('code', '5-2911')->update([
            'name' => 'Pembulatan Kas (Rugi)',
        ]);

        $this->ensureAccount(
            '4-2006',
            'Pembulatan Kas (Laba)',
            Account::TYPE_REVENUE,
            Account::SUBTYPE_OTHER_REVENUE,
            '4-2000',
        );
    }

    public function down(): void
    {
        Account::query()->where('code', '5-2911')->update([
            'name' => 'Pembulatan Kas',
        ]);
        Account::query()->where('code', '4-2006')->delete();
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
