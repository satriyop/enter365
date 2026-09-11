<?php

namespace App\Services\Accounting;

use App\Models\Accounting\PaymentTerm;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PaymentTermSchedule
{
    /** @return list<array{due_date: string, amount: int}> */
    public function calculate(PaymentTerm $term, int $amount, string $date): array
    {
        $invoiceDate = CarbonImmutable::parse($date);
        $remaining = $amount;
        $schedule = [];
        foreach ($term->lines as $line) {
            $value = (int) round((float) $line['value'] * 100);
            $installment = match ($line['type']) {
                'percent' => intdiv($amount * $value + 5000, 10000),
                'fixed' => $value,
                'balance' => $remaining,
            };
            if ($installment > $remaining) {
                throw ValidationException::withMessages(['amount' => 'The installments exceed the preview amount.']);
            }
            $remaining -= $installment;
            $dueDate = match ($line['due_type']) {
                'days_after' => $invoiceDate->addDays($line['days']),
                'end_of_month' => $invoiceDate->endOfMonth()->addDays($line['days']),
                'end_of_next_month' => $invoiceDate->addMonthNoOverflow()->endOfMonth()->addDays($line['days']),
            };
            $schedule[] = ['due_date' => $dueDate->toDateString(), 'amount' => $installment];
        }

        return $schedule;
    }
}
