<?php

use App\Models\Accounting\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Add PPh 4(2) and PPh 26 payable accounts as children of 2-1300 (Utang Pajak).
     * Existing: 2-1301 (PPh 21), 2-1302 (PPh 23), 2-1303 (PPh 25), 2-1304 (PPh Badan).
     */
    public function up(): void
    {
        $parentAccount = Account::where('code', '2-1300')->first();
        if (! $parentAccount) {
            return;
        }

        $newAccounts = [
            ['code' => '2-1305', 'name' => 'Utang PPh 4(2)'],
            ['code' => '2-1306', 'name' => 'Utang PPh 26'],
        ];

        foreach ($newAccounts as $accountData) {
            if (! Account::where('code', $accountData['code'])->exists()) {
                Account::create([
                    'code' => $accountData['code'],
                    'name' => $accountData['name'],
                    'type' => Account::TYPE_LIABILITY,
                    'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
                    'parent_id' => $parentAccount->id,
                    'is_system' => false,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Account::whereIn('code', ['2-1305', '2-1306'])->delete();
    }
};
