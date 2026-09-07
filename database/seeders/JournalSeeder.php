<?php

namespace Database\Seeders;

use App\Models\Accounting\Journal;
use Illuminate\Database\Seeder;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Sales', 'type' => Journal::TYPE_SALES, 'sequence_prefix' => 'SALE-'],
            ['name' => 'Purchase', 'type' => Journal::TYPE_PURCHASE, 'sequence_prefix' => 'PURC-'],
            ['name' => 'Bank', 'type' => Journal::TYPE_BANK, 'sequence_prefix' => 'BNK-'],
            ['name' => 'Cash', 'type' => Journal::TYPE_CASH, 'sequence_prefix' => 'CSH-'],
            ['name' => 'Miscellaneous', 'type' => Journal::TYPE_MISCELLANEOUS, 'sequence_prefix' => 'MISC-'],
        ];

        foreach ($defaults as $row) {
            Journal::firstOrCreate(
                ['sequence_prefix' => $row['sequence_prefix']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'default_account_id' => null,
                    'currency' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
