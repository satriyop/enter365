<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for SolarProposal queries.
 */
class SolarProposalFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['proposal_number', 'site_name', 'site_address', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $value): void
    {
        $search = strtolower($value);
        $this->builder->where(function ($query) use ($search) {
            $query->whereRaw('LOWER(proposal_number) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(site_name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(site_address) LIKE ?', ["%{$search}%"])
                ->orWhereHas('contact', function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                });
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'created_at';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'proposal_number',
            'status',
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
            'variantGroup',
            'selectedBom',
            'creator',
            'convertedQuotation',
        ];
    }

    /**
     * Filter by contact ID.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by province.
     */
    public function province(string $value): void
    {
        $this->builder->where('province', $value);
    }

    /**
     * Filter by city.
     */
    public function city(string $value): void
    {
        $this->builder->where('city', $value);
    }

    /**
     * Filter only active proposals.
     */
    public function activeOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->active();
        }
    }

    /**
     * Filter only expired proposals.
     */
    public function expiredOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->where('status', \App\Enums\DocumentStatus::Expired);
        }
    }
}
