<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ElectricalPanel;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\BomTemplate;
use App\Services\ElectricalPanel\BomTemplateService;
use Illuminate\Http\JsonResponse;

/**
 * Brand-aware BOM template endpoints (electrical_panel add-on only).
 */
class BomTemplateBrandController extends Controller
{
    public function __construct(
        private BomTemplateService $templateService
    ) {}

    /**
     * Brands available for resolving template lines via component standards.
     *
     * @response array{data: array<array{code: string, name: string, coverage: int, coverage_percent: float}>, meta: array{template_id: int, template_code: string, items_with_standard: int}}
     */
    public function availableBrands(BomTemplate $bomTemplate): JsonResponse
    {
        $brands = $this->templateService->getAvailableBrandsForTemplate($bomTemplate);

        $itemsWithStandard = (int) $bomTemplate->items()
            ->whereHas('panelMeta', fn ($q) => $q->whereNotNull('component_standard_id'))
            ->count();

        return response()->json([
            'data' => $brands,
            'meta' => [
                'template_id' => $bomTemplate->id,
                'template_code' => $bomTemplate->code,
                'items_with_standard' => $itemsWithStandard,
            ],
        ]);
    }
}
