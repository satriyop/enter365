<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Infrastructure\Repositories\InMemory\InMemoryInvoiceRepository;

/**
 * Trait for testing with in-memory repositories.
 *
 * Provides methods to swap Eloquent repositories with
 * in-memory implementations for faster, isolated tests.
 *
 * Usage in Pest:
 * ```php
 * uses(InteractsWithRepositories::class);
 *
 * beforeEach(function () {
 *     $this->setUpInMemoryRepositories();
 * });
 *
 * it('finds invoice by status', function () {
 *     $this->seedInvoices([...]);
 *     $results = $this->invoiceRepository->findByStatus(DocumentStatus::Draft);
 * });
 * ```
 */
trait InteractsWithRepositories
{
    protected InMemoryInvoiceRepository $invoiceRepository;

    /**
     * Set up in-memory repositories.
     * Call this in beforeEach().
     */
    protected function setUpInMemoryRepositories(): void
    {
        $this->invoiceRepository = new InMemoryInvoiceRepository;
        $this->app->instance(InvoiceRepositoryInterface::class, $this->invoiceRepository);
    }

    /**
     * Seed the in-memory invoice repository with test data.
     *
     * @param  array<\App\Models\Sales\Invoice>  $invoices
     */
    protected function seedInvoices(array $invoices): void
    {
        foreach ($invoices as $invoice) {
            $this->invoiceRepository->add($invoice);
        }
    }

    /**
     * Clear all data from the in-memory invoice repository.
     */
    protected function clearInvoiceRepository(): void
    {
        $this->invoiceRepository->clear();
    }

    /**
     * Get the in-memory invoice repository for assertions.
     */
    protected function getInvoiceRepository(): InMemoryInvoiceRepository
    {
        return $this->invoiceRepository;
    }
}
