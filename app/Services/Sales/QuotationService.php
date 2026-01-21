<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Sales\QuotationServiceInterface;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationVariantOption;
use App\Models\User;
use App\Services\Sales\Quotation\QuotationCrudService;
use App\Services\Sales\Quotation\QuotationStatisticsService;
use App\Services\Sales\Quotation\QuotationWorkflowService;
use App\Support\OperationContext;
use Illuminate\Support\Collection;

/**
 * Quotation service coordinator.
 *
 * This is a thin coordinator that delegates to focused services:
 * - QuotationCrudService: create, update, delete, duplicate, revise
 * - QuotationWorkflowService: submit, approve, reject, cancel, markAsSent, markExpired
 * - QuotationStatisticsService: getStatistics
 * - QuotationConversionService: convertToInvoice
 *
 * The coordinator pattern maintains backward compatibility while allowing
 * the focused services to be used directly when appropriate.
 *
 * @see \App\Services\Sales\Quotation\QuotationCrudService
 * @see \App\Services\Sales\Quotation\QuotationWorkflowService
 * @see \App\Services\Sales\Quotation\QuotationStatisticsService
 * @see \App\Services\Sales\QuotationConversionService
 */
class QuotationService implements QuotationServiceInterface
{
    public function __construct(
        private QuotationCrudService $crud,
        private QuotationWorkflowService $workflow,
        private QuotationStatisticsService $statistics,
        private QuotationConversionService $conversion,
    ) {}

    /**
     * Set operation context for all underlying services.
     *
     * Returns a clone with context-aware services for fluent chaining.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->workflow = $this->workflow->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD Operations (delegated to QuotationCrudService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a new quotation with items.
     *
     * @param  array<string, mixed>  $data
     * @param  User|int|null  $user  The user creating the quotation
     */
    public function create(array $data, User|int|null $user = null): Quotation
    {
        return $this->crud->create($data, $user);
    }

    /**
     * Create a quotation from a BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromBom(array $data): Quotation
    {
        return $this->crud->createFromBom($data);
    }

    /**
     * Update a quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return $this->crud->update($quotation, $data);
    }

    /**
     * Delete a quotation.
     */
    public function delete(Quotation $quotation): bool
    {
        return $this->crud->delete($quotation);
    }

    /**
     * Duplicate a quotation as a new draft.
     */
    public function duplicate(Quotation $quotation): Quotation
    {
        return $this->crud->duplicate($quotation);
    }

    /**
     * Create a revision of a quotation.
     */
    public function revise(Quotation $quotation): Quotation
    {
        return $this->crud->revise($quotation);
    }

    // ─────────────────────────────────────────────────────────────
    // Workflow Operations (delegated to QuotationWorkflowService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        return $this->workflow->submit($quotation, $userId);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation
    {
        return $this->workflow->approve($quotation, $userId);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation
    {
        return $this->workflow->reject($quotation, $reason, $userId);
    }

    /**
     * Cancel a quotation.
     */
    public function cancel(Quotation $quotation, ?string $reason = null, ?int $userId = null): Quotation
    {
        return $this->workflow->cancel($quotation, $reason, $userId);
    }

    /**
     * Mark quotation as sent to customer.
     */
    public function markAsSent(
        Quotation $quotation,
        ?string $email = null,
        ?string $via = 'email'
    ): Quotation {
        return $this->workflow->markAsSent($quotation, $email, $via);
    }

    /**
     * Mark expired quotations.
     *
     * @return int Number of quotations marked as expired
     */
    public function markExpired(): int
    {
        return $this->workflow->markExpired();
    }

    // ─────────────────────────────────────────────────────────────
    // Conversion (delegated to QuotationConversionService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Convert an approved quotation to an invoice.
     */
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        return $this->conversion->convertToInvoice($quotation);
    }

    // ─────────────────────────────────────────────────────────────
    // Statistics (delegated to QuotationStatisticsService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->statistics->get($startDate, $endDate);
    }

    // ─────────────────────────────────────────────────────────────
    // Variant Options (delegated to QuotationCrudService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Sync variant options for a multi-option quotation.
     *
     * @param  array<int, array{bom_id: int, display_name: string, tagline?: string, is_recommended?: bool, selling_price: int, features?: array, specifications?: array, warranty_terms?: string}>  $options
     * @return Collection<int, QuotationVariantOption>
     */
    public function syncVariantOptions(Quotation $quotation, array $options): Collection
    {
        return $this->crud->syncVariantOptions($quotation, $options);
    }

    /**
     * Select a variant for a multi-option quotation.
     */
    public function selectVariant(Quotation $quotation, int $variantOptionId): Quotation
    {
        return $this->crud->selectVariant($quotation, $variantOptionId);
    }

    /**
     * Clear the selected variant for a multi-option quotation.
     */
    public function clearVariantSelection(Quotation $quotation): Quotation
    {
        return $this->crud->clearVariantSelection($quotation);
    }
}
