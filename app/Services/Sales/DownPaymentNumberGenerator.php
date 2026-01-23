<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\DownPayment;

/**
 * Down Payment number generator with type context.
 */
class DownPaymentNumberGenerator
{
    public function __construct() {}

    /**
     * Generate DP number with type-based prefix.
     */
    public function generate(string $type): string
    {
        $prefix = $type === DownPayment::TYPE_RECEIVABLE ? 'DPR-' : 'DPP-';
        $prefix .= now()->format('Ym').'-';

        $lastDp = DownPayment::query()
            ->where('dp_number', 'like', $prefix.'%')
            ->orderBy('dp_number', 'desc')
            ->first();

        if ($lastDp && preg_match('/-(\d+)$/', $lastDp->dp_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
