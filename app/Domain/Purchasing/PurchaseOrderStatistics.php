<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Database\Eloquent\Collection;

class PurchaseOrderStatistics
{
    public function getOutstanding(?int $contactId = null): Collection
    {
        $query = PurchaseOrder::query()
            ->with(['contact', 'items'])
            ->outstanding()
            ->orderBy('expected_date');

        if ($contactId) {
            $query->where('contact_id', $contactId);
        }

        return $query->get();
    }

    public function getStatistics(?string $startDate, ?string $endDate): array
    {
        $query = PurchaseOrder::query();

        if ($startDate) {
            $query->where('po_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('po_date', '<=', $endDate);
        }

        $total = (clone $query)->count();
        $draft = (clone $query)->where('status', DocumentStatus::Draft)->count();
        $submitted = (clone $query)->where('status', DocumentStatus::Submitted)->count();
        $approved = (clone $query)->where('status', DocumentStatus::Approved)->count();
        $rejected = (clone $query)->where('status', DocumentStatus::Rejected)->count();
        $partial = (clone $query)->where('status', DocumentStatus::Partial)->count();
        $received = (clone $query)->where('status', DocumentStatus::Received)->count();
        $cancelled = (clone $query)->where('status', DocumentStatus::Cancelled)->count();

        $totalValue = (clone $query)->sum('total_amount');
        $outstandingValue = (clone $query)->whereIn('status', [
            DocumentStatus::Approved,
            DocumentStatus::Partial,
        ])->sum('total_amount');

        return [
            'total' => $total,
            'by_status' => [
                'draft' => $draft,
                'submitted' => $submitted,
                'approved' => $approved,
                'rejected' => $rejected,
                'partial' => $partial,
                'received' => $received,
                'cancelled' => $cancelled,
            ],
            'total_value' => $totalValue,
            'outstanding_value' => $outstandingValue,
        ];
    }
}
