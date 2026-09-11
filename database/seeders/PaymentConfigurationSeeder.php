<?php

namespace Database\Seeders;

use App\Models\Accounting\PaymentMethod;
use App\Models\Accounting\PaymentTerm;
use Illuminate\Database\Seeder;

class PaymentConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([0, 15, 30, 60] as $days) {
            PaymentTerm::query()->firstOrCreate(['code' => 'NET-'.$days], [
                'name' => $days === 0 ? 'Immediate Payment' : 'Net '.$days.' Days',
                'lines' => [['type' => 'balance', 'value' => 0, 'days' => $days, 'due_type' => 'days_after']],
            ]);
        }
        foreach (['inbound', 'outbound'] as $direction) {
            foreach (['cash', 'bank_transfer', 'check'] as $type) {
                PaymentMethod::query()->firstOrCreate(['code' => strtoupper($direction.'-'.$type)], [
                    'name' => ucwords(str_replace('_', ' ', $direction.' '.$type)),
                    'direction' => $direction,
                    'payment_type' => $type,
                ]);
            }
        }
    }
}
