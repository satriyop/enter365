<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Contact queries.
 */
class ContactFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'email', 'phone', 'code'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'name', 'code', 'type', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'invoices',
            'bills',
            'quotations',
            'purchaseOrders',
            'payments',
            'downPayments',
        ];
    }

    /**
     * Filter by contact type.
     */
    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    /**
     * Filter by active status.
     */
    public function isActive(bool|string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
