<?php

declare(strict_types=1);

namespace App\Services\Solar\Proposal;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Sales\Quotation;
use App\Models\Solar\SolarProposal;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;

/**
 * Handles solar proposal workflow transitions and conversion to quotation.
 *
 * @see \App\Services\Solar\SolarProposalService The coordinator service
 */
class SolarProposalWorkflowService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        protected QuotationServiceInterface $quotationService,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Mark proposal as sent to customer.
     */
    public function send(SolarProposal $proposal): SolarProposal
    {
        $stateMachine = $proposal->stateMachine();

        if (! $stateMachine->canSend()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Proposal Solar',
                'dikirim',
                $proposal->status->value,
                'draft dengan variant group dan kalkulasi lengkap'
            );
        }

        return $this->executeInTransaction('send', function () use ($proposal) {
            // Use state machine for status transition
            // State machine handles sent_at, public_token, public_token_expires_at
            $proposal->transitionTo(DocumentStatus::Sent, $this->getUserId());

            return $proposal->fresh();
        }, ['proposal_id' => $proposal->id]);
    }

    /**
     * Mark proposal as accepted by customer.
     *
     * @param  int|null  $selectedBomId  The BOM variant customer selected (if multi-option)
     */
    public function accept(SolarProposal $proposal, ?int $selectedBomId = null): SolarProposal
    {
        $stateMachine = $proposal->stateMachine();

        if (! $stateMachine->canAccept()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Proposal Solar',
                'diterima',
                $proposal->status->value,
                'sent'
            );
        }

        return $this->executeInTransaction('accept', function () use ($proposal, $selectedBomId) {
            // Update selected BOM if customer chose a different variant
            if ($selectedBomId && $selectedBomId !== $proposal->selected_bom_id) {
                $bom = Bom::find($selectedBomId);
                if ($bom && $bom->variant_group_id === $proposal->variant_group_id) {
                    $proposal->selected_bom_id = $selectedBomId;
                    $proposal->save();
                }
            }

            // Use state machine for status transition
            // State machine handles accepted_at
            $proposal->transitionTo(DocumentStatus::Accepted);

            return $proposal->fresh(['contact', 'selectedBom']);
        }, ['proposal_id' => $proposal->id]);
    }

    /**
     * Mark proposal as rejected by customer.
     */
    public function reject(SolarProposal $proposal, ?string $reason = null): SolarProposal
    {
        $stateMachine = $proposal->stateMachine();

        if (! $stateMachine->canReject()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Proposal Solar',
                'ditolak',
                $proposal->status->value,
                'sent'
            );
        }

        return $this->executeInTransaction('reject', function () use ($proposal, $reason) {
            // Use state machine for status transition
            // State machine handles rejected_at and rejection_reason via context
            $proposal->transitionTo(DocumentStatus::Rejected, null, ['rejection_reason' => $reason]);

            return $proposal->fresh();
        }, ['proposal_id' => $proposal->id]);
    }

    /**
     * Convert accepted proposal to a quotation.
     *
     * Creates a quotation from the selected BOM with the proposal's pricing.
     */
    public function convertToQuotation(SolarProposal $proposal): Quotation
    {
        if (! $proposal->canConvert()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Proposal Solar',
                'dikonversi ke quotation',
                $proposal->status->value,
                'accepted dengan BOM yang dipilih'
            );
        }

        return $this->executeInTransaction('convert_to_quotation', function () use ($proposal) {
            $bom = $proposal->selectedBom;

            // Create quotation from the selected BOM
            $quotation = $this->quotationService->createFromBom([
                'bom_id' => $bom->id,
                'contact_id' => $proposal->contact_id,
                'expand_items' => false, // Keep as single line for solar systems
                'subject' => 'Sistem Panel Surya - '.$proposal->site_name,
                'notes' => $this->buildQuotationNotes($proposal),
                'reference' => $proposal->proposal_number,
            ]);

            // Link proposal to quotation
            $proposal->converted_quotation_id = $quotation->id;
            $proposal->save();

            return $quotation;
        }, ['proposal_id' => $proposal->id]);
    }

    /**
     * Build notes for quotation from proposal data.
     */
    protected function buildQuotationNotes(SolarProposal $proposal): string
    {
        $notes = [];

        $notes[] = "Dibuat dari Solar Proposal: {$proposal->proposal_number}";
        $notes[] = '';

        if ($proposal->site_address) {
            $notes[] = "Lokasi: {$proposal->site_address}";
        }

        if ($proposal->system_capacity_kwp) {
            $notes[] = "Kapasitas Sistem: {$proposal->system_capacity_kwp} kWp";
        }

        if ($proposal->annual_production_kwh) {
            $notes[] = 'Estimasi Produksi: '.number_format((float) $proposal->annual_production_kwh).' kWh/tahun';
        }

        if ($proposal->getPaybackPeriod()) {
            $notes[] = "Payback Period: {$proposal->getPaybackPeriod()} tahun";
        }

        if ($proposal->getRoi()) {
            $notes[] = "ROI (25 tahun): {$proposal->getRoi()}%";
        }

        return implode("\n", $notes);
    }
}
