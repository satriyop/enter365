<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Shared\Payment;

/**
 * Payment number generator with type context.
 */
class PaymentNumberGenerator
{
    /**
     * Generate payment number with type-based prefix.
     */
    public function generate(string $type): string
    {
        $prefix = ($type === Payment::TYPE_RECEIVE ? 'RCV' : 'PAY').'-'.now()->format('Ym').'-';

        $lastPayment = Payment::query()
            ->where('payment_number', 'like', $prefix.'%')
            ->orderBy('payment_number', 'desc')
            ->first();

        if ($lastPayment && preg_match('/-(\d+)$/', $lastPayment->payment_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
