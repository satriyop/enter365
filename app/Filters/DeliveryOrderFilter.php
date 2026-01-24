<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for DeliveryOrder queries.
 *
 * Supported filters:
 * - status: Delivery order status (draft, confirmed, shipped, delivered, cancelled)
 * - contact_id: Filter by customer contact
 * - invoice_id: Filter by related invoice
 * - warehouse_id: Filter by source warehouse
 * - start_date: DO date from
 * - end_date: DO date to
 * - search: Search by DO number, tracking number, driver name, or contact name
 */
class DeliveryOrderFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['do_number', 'tracking_number', 'driver_name', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'do_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'do_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'do_number',
            'do_date',
            'status',
            'shipping_date',
            'delivered_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'contact',
            'warehouse',
            'items',
            'items.product',
            'invoice',
            'creator',
        ];
    }

    /**
     * Filter by customer contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by related invoice.
     */
    public function invoiceId(int|string $value): void
    {
        $this->builder->where('invoice_id', $value);
    }

    /**
     * Filter by source warehouse.
     */
    public function warehouseId(int|string $value): void
    {
        $this->builder->where('warehouse_id', $value);
    }
}
