<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Purchasing\GoodsReceiptNote;

/**
 * Goods Receipt Note number generator with date-based prefix.
 */
class GoodsReceiptNoteNumberGenerator
{
    /**
     * Generate GRN number with date-based prefix.
     */
    public function generate(): string
    {
        $date = now()->format('Ymd');
        $prefix = "GRN-{$date}-";

        $lastGrn = GoodsReceiptNote::where('grn_number', 'like', "{$prefix}%")
            ->orderByDesc('grn_number')
            ->first();

        if ($lastGrn && preg_match('/(\d{4})$/', $lastGrn->grn_number, $matches)) {
            $nextNum = (int) $matches[1] + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }
}
