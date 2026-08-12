<?php

declare(strict_types=1);

namespace App\Services\Solar;

use App\Contracts\Solar\SolarProposalServiceInterface;
use App\Models\Sales\Quotation;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\SolarProposal;
use App\Services\Solar\Proposal\SolarProposalBomService;
use App\Services\Solar\Proposal\SolarProposalCalculationService;
use App\Services\Solar\Proposal\SolarProposalCrudService;
use App\Services\Solar\Proposal\SolarProposalWorkflowService;
use App\Support\OperationContext;

/**
 * Solar proposal service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - SolarProposalCrudService: create, update, delete
 * - SolarProposalCalculationService: calculateProposal
 * - SolarProposalWorkflowService: send, accept, reject, convertToQuotation
 * - SolarProposalBomService: attachVariantGroup, selectBom, lookupSolarData*
 *
 * @see \App\Services\Solar\Proposal\SolarProposalCrudService
 * @see \App\Services\Solar\Proposal\SolarProposalCalculationService
 * @see \App\Services\Solar\Proposal\SolarProposalWorkflowService
 * @see \App\Services\Solar\Proposal\SolarProposalBomService
 */
class SolarProposalService implements SolarProposalServiceInterface
{
    public function __construct(
        private SolarProposalCrudService $crud,
        private SolarProposalCalculationService $calculation,
        private SolarProposalWorkflowService $workflow,
        private SolarProposalBomService $bom,
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
        $clone->calculation = $this->calculation->withContext($context);
        $clone->workflow = $this->workflow->withContext($context);
        $clone->bom = $this->bom->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD Operations (delegated to SolarProposalCrudService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function create(array $data): SolarProposal
    {
        return $this->crud->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(SolarProposal $proposal, array $data): SolarProposal
    {
        return $this->crud->update($proposal, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(SolarProposal $proposal): bool
    {
        return $this->crud->delete($proposal);
    }

    // ─────────────────────────────────────────────────────────────
    // Calculation (delegated to SolarProposalCalculationService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function calculateProposal(SolarProposal $proposal): SolarProposal
    {
        return $this->calculation->calculateProposal($proposal);
    }

    // ─────────────────────────────────────────────────────────────
    // BOM / Lookup (delegated to SolarProposalBomService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function attachVariantGroup(SolarProposal $proposal, int $variantGroupId): SolarProposal
    {
        return $this->bom->attachVariantGroup($proposal, $variantGroupId);
    }

    /**
     * {@inheritdoc}
     */
    public function selectBom(SolarProposal $proposal, int $bomId): SolarProposal
    {
        return $this->bom->selectBom($proposal, $bomId);
    }

    /**
     * {@inheritdoc}
     */
    public function lookupSolarData(string $province, string $city): ?IndonesiaSolarData
    {
        return $this->bom->lookupSolarData($province, $city);
    }

    /**
     * {@inheritdoc}
     */
    public function lookupSolarDataByCoordinates(float $latitude, float $longitude): ?IndonesiaSolarData
    {
        return $this->bom->lookupSolarDataByCoordinates($latitude, $longitude);
    }

    // ─────────────────────────────────────────────────────────────
    // Workflow (delegated to SolarProposalWorkflowService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function send(SolarProposal $proposal): SolarProposal
    {
        return $this->workflow->send($proposal);
    }

    /**
     * {@inheritdoc}
     */
    public function accept(SolarProposal $proposal, ?int $selectedBomId = null): SolarProposal
    {
        return $this->workflow->accept($proposal, $selectedBomId);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(SolarProposal $proposal, ?string $reason = null): SolarProposal
    {
        return $this->workflow->reject($proposal, $reason);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToQuotation(SolarProposal $proposal): Quotation
    {
        return $this->workflow->convertToQuotation($proposal);
    }
}
