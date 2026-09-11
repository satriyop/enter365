<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingTransfer;
use App\Models\Accounting\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingTransfer>
 */
class AccountingTransferFactory extends Factory
{
    protected $model = AccountingTransfer::class;

    public function definition(): array
    {
        return [
            'transfer_number' => strtoupper(fake()->unique()->bothify('TRF-####')),
            'transfer_date' => now()->toDateString(),
            'from_journal_id' => Journal::factory()->bank(),
            'to_journal_id' => Journal::factory()->miscellaneous(),
            'from_account_id' => Account::factory()->asset(),
            'to_account_id' => Account::factory()->asset(),
            'amount' => 100_000,
            'status' => AccountingTransfer::STATUS_DRAFT,
        ];
    }
}
