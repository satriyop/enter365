<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\MaterialRequisition;

/**
 * Material Requisition number generator with date-based prefix.
 */
class MaterialRequisitionNumberGenerator
{
    /**
     * Generate MR number with date-based prefix.
     */
    public function generate(): string
    {
        $prefix = 'MR-'.now()->format('Ym').'-';
        $lastMr = MaterialRequisition::query()
            ->where('requisition_number', 'like', $prefix.'%')
            ->orderByDesc('requisition_number')
            ->first();

        if ($lastMr && preg_match('/-(\d{4})$/', $lastMr->requisition_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
