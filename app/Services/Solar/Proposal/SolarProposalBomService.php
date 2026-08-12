<?php

declare(strict_types=1);

namespace App\Services\Solar\Proposal;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\SolarProposal;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use App\Services\Solar\SolarCalculationService;
use App\Support\OperationContext;

/**
 * Handles BOM / variant group attachment and solar data lookup for proposals.
 *
 * @see \App\Services\Solar\SolarProposalService The coordinator service
 */
class SolarProposalBomService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        protected SolarCalculationService $calculator,
        protected SolarProposalCalculationService $calculation,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Propagate operation context to nested calculation service.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->operationContext = $context;
        $clone->calculation = $this->calculation->withContext($context);

        return $clone;
    }

    /**
     * Attach a BOM variant group to the proposal.
     *
     * This links the proposal to a set of Budget/Standard/Premium options.
     */
    public function attachVariantGroup(SolarProposal $proposal, int $variantGroupId): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($proposal, 'Proposal hanya dapat diedit dalam status draft.');
        }

        $variantGroup = BomVariantGroup::with('activeBoms')->find($variantGroupId);
        if (! $variantGroup) {
            throw new \App\Exceptions\Domain\EntityNotFoundException('BomVariantGroup', $variantGroupId);
        }

        return $this->executeInTransaction('attach_variant_group', function () use ($proposal, $variantGroup) {
            $proposal->variant_group_id = $variantGroup->id;

            // Auto-select the primary (recommended) BOM if available
            $primaryBom = $variantGroup->primaryBom();
            if ($primaryBom) {
                $proposal->selected_bom_id = $primaryBom->id;
                $capacity = $this->extractCapacityFromBom($primaryBom);
                $proposal->system_capacity_kwp = $capacity !== null ? (string) $capacity : null;
            }

            $proposal->save();

            // Recalculate with new system
            return $this->calculation->calculateProposal($proposal);
        }, ['proposal_id' => $proposal->id, 'variant_group_id' => $variantGroup->id]);
    }

    /**
     * Select a specific BOM from the variant group.
     */
    public function selectBom(SolarProposal $proposal, int $bomId): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($proposal, 'Proposal hanya dapat diedit dalam status draft.');
        }

        $bom = Bom::find($bomId);
        if (! $bom) {
            throw new \App\Exceptions\Domain\EntityNotFoundException('Bom', $bomId);
        }

        // Verify BOM belongs to the attached variant group (if any)
        if ($proposal->variant_group_id && $bom->variant_group_id !== $proposal->variant_group_id) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'memilih BOM',
                'BOM tidak termasuk dalam variant group yang dipilih'
            );
        }

        return $this->executeInTransaction('select_bom', function () use ($proposal, $bom) {
            $proposal->selected_bom_id = $bom->id;
            $capacity = $this->extractCapacityFromBom($bom);
            $proposal->system_capacity_kwp = $capacity !== null ? (string) $capacity : null;

            // If no variant group attached, attach it now
            if (! $proposal->variant_group_id && $bom->variant_group_id) {
                $proposal->variant_group_id = $bom->variant_group_id;
            }

            $proposal->save();

            return $this->calculation->calculateProposal($proposal);
        }, ['proposal_id' => $proposal->id, 'bom_id' => $bom->id]);
    }

    /**
     * Get solar data lookup for location.
     */
    public function lookupSolarData(string $province, string $city): ?IndonesiaSolarData
    {
        return $this->calculator->getSolarDataByLocation($province, $city);
    }

    /**
     * Get solar data by coordinates.
     */
    public function lookupSolarDataByCoordinates(float $latitude, float $longitude): ?IndonesiaSolarData
    {
        return $this->calculator->getSolarDataByCoordinates($latitude, $longitude);
    }

    /**
     * Extract system capacity from BOM.
     *
     * Looks for kWp in the BOM name or calculates from panel count.
     */
    protected function extractCapacityFromBom(Bom $bom): ?float
    {
        // Try to extract from BOM name (e.g., "10 kWp Solar System")
        if (preg_match('/(\d+(?:\.\d+)?)\s*k[Ww][Pp]/i', $bom->name, $matches)) {
            return (float) $matches[1];
        }

        // Try variant name
        if ($bom->variant_name && preg_match('/(\d+(?:\.\d+)?)\s*k[Ww][Pp]/i', $bom->variant_name, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
