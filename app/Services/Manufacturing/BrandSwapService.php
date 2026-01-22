<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Manufacturing\SpecValidationRuleSet;
use App\Services\Base\BaseService;
use App\Services\Manufacturing\BrandSwap\BrandSwapExecutionService;
use App\Services\Manufacturing\BrandSwap\BrandSwapPreviewService;
use Illuminate\Support\Collection;

/**
 * Coordinator service for brand swap operations on BOMs.
 *
 * Delegates to specialized services:
 * - BrandSwapPreviewService: Read-only preview and comparison operations
 * - BrandSwapExecutionService: Write operations for executing swaps
 *
 * This facade maintains backward compatibility while keeping responsibilities focused.
 */
class BrandSwapService extends BaseService
{
    public function __construct(
        private BrandSwapPreviewService $previewService,
        private BrandSwapExecutionService $executionService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    // =========================================================================
    // Preview Operations (delegated to BrandSwapPreviewService)
    // =========================================================================

    /**
     * Preview swap without creating a new BOM.
     *
     * @return array{current_total: float, estimated_total: float, savings: float, savings_percentage: float, coverage: array, items: array, validation: array}
     */
    public function previewSwapBrand(Bom $bom, string $targetBrand, ?SpecValidationRuleSet $ruleSet = null): array
    {
        return $this->previewService->previewSwapBrand($bom, $targetBrand, $ruleSet);
    }

    /**
     * Compare BOM across all available brands at once.
     *
     * @return array{current: array, brands: array, best_value: string|null, best_coverage: string|null}
     */
    public function compareBrands(Bom $bom): array
    {
        return $this->previewService->compareBrands($bom);
    }

    /**
     * Preview a single item swap without persisting.
     *
     * @return array{can_swap: bool, estimated_unit_cost: float, target_product: string|null, target_sku: string|null}
     */
    public function previewItemSwap(BomItem $item, string $targetBrand): array
    {
        return $this->previewService->previewItemSwap($item, $targetBrand);
    }

    /**
     * Get available alternatives for a BOM item.
     *
     * @return array{alternatives: array, current: array}
     */
    public function getItemAlternatives(BomItem $item): array
    {
        return $this->previewService->getItemAlternatives($item);
    }

    // =========================================================================
    // Execution Operations (delegated to BrandSwapExecutionService)
    // =========================================================================

    /**
     * Swap all components in a BOM to a different brand.
     *
     * @return array{bom: Bom, swap_report: array<string, mixed>}
     */
    public function swapBomBrand(
        Bom $bom,
        string $targetBrand,
        bool $createVariant = true,
        ?BomVariantGroup $variantGroup = null
    ): array {
        return $this->executionService->swapBomBrand($bom, $targetBrand, $createVariant, $variantGroup);
    }

    /**
     * Generate all brand variants for a BOM at once.
     *
     * @param  array<string>  $brands
     * @return array{variant_group: BomVariantGroup, boms: Collection<int, Bom>, report: array<string, mixed>}
     */
    public function generateBrandVariants(
        Bom $bom,
        array $brands,
        ?string $groupName = null
    ): array {
        return $this->executionService->generateBrandVariants($bom, $brands, $groupName);
    }

    /**
     * Quick swap a BOM item to a different product (in-place edit).
     *
     * @return array{success: bool, item: BomItem, previous: array, savings: float}
     */
    public function quickSwapItem(BomItem $item, Product $newProduct, ?string $reason = null): array
    {
        return $this->executionService->quickSwapItem($item, $newProduct, $reason);
    }
}
