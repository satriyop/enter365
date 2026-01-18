<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\RecurringTemplate;
use Illuminate\Support\Collection;

/**
 * Interface for Recurring Template service operations.
 */
interface RecurringServiceInterface
{
    /**
     * Generate all documents for due recurring templates.
     *
     * @return Collection<int, Invoice|Bill>
     */
    public function generateDueDocuments(): Collection;

    /**
     * Generate a single document from a template.
     */
    public function generateFromTemplate(RecurringTemplate $template): Invoice|Bill|null;

    /**
     * Create a recurring template from an invoice.
     *
     * @param  array<string, mixed>  $scheduleData
     */
    public function createTemplateFromInvoice(Invoice $invoice, array $scheduleData): RecurringTemplate;

    /**
     * Create a recurring template from a bill.
     *
     * @param  array<string, mixed>  $scheduleData
     */
    public function createTemplateFromBill(Bill $bill, array $scheduleData): RecurringTemplate;
}
