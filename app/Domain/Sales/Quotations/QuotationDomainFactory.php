<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Sales\QuotationCalculatorInterface;
use App\Models\Sales\Quotation;

/**
 * Factory for creating quotation domain objects with proper dependency injection.
 *
 * Centralizes domain object creation, replacing scattered `new` calls and
 * service locator patterns inside the Quotation model.
 *
 * @example
 * // In a service:
 * public function __construct(private QuotationDomainFactory $domainFactory) {}
 *
 * public function submit(Quotation $quotation): Quotation
 * {
 *     $stateMachine = $this->domainFactory->stateMachine($quotation);
 *     if (!$stateMachine->canSubmit()) {
 *         throw new InvalidArgumentException('Cannot submit');
 *     }
 *     $stateMachine->transitionTo(DocumentStatus::Submitted);
 * }
 */
class QuotationDomainFactory
{
    private ?OutcomeManager $outcomeManager = null;

    private ?FollowUpManager $followUpManager = null;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private QuotationCalculatorInterface $calculator,
    ) {}

    /**
     * Create a state machine for the given quotation.
     *
     * The state machine is created fresh each time since it holds
     * quotation-specific state.
     */
    public function stateMachine(Quotation $quotation): QuotationStateMachine
    {
        return QuotationStateMachine::fromQuotation($quotation, $this->eventDispatcher);
    }

    /**
     * Get the outcome manager (singleton within factory lifetime).
     *
     * OutcomeManager is stateless and can be safely reused.
     */
    public function outcomeManager(): OutcomeManager
    {
        return $this->outcomeManager ??= new OutcomeManager;
    }

    /**
     * Get the follow-up manager (singleton within factory lifetime).
     *
     * FollowUpManager is stateless and can be safely reused.
     */
    public function followUpManager(): FollowUpManager
    {
        return $this->followUpManager ??= new FollowUpManager;
    }

    /**
     * Get the quotation calculator.
     */
    public function calculator(): QuotationCalculatorInterface
    {
        return $this->calculator;
    }

    /**
     * Calculate totals for a quotation.
     *
     * Convenience method that encapsulates the calculation logic.
     *
     * @return QuotationTotals The calculated totals DTO
     */
    public function calculateTotals(Quotation $quotation): QuotationTotals
    {
        $lineTotals = $quotation->items->pluck('line_total')->toArray();

        // Cast decimal/string values to proper types for calculator
        return $this->calculator->calculate(
            $lineTotals,
            (float) ($quotation->tax_rate ?? 0),
            $quotation->discount_type,
            (float) ($quotation->discount_value ?? 0),
            $quotation->currency,
            (float) ($quotation->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to a quotation.
     *
     * Updates the quotation's financial fields without saving.
     */
    public function applyTotals(Quotation $quotation): Quotation
    {
        $totals = $this->calculateTotals($quotation);

        $quotation->subtotal = $totals->subtotal;
        $quotation->discount_amount = $totals->discountAmount;
        $quotation->tax_amount = $totals->taxAmount;
        $quotation->total_amount = $totals->totalAmount;
        $quotation->base_currency_total = $totals->baseCurrencyTotal;

        return $quotation;
    }
}
