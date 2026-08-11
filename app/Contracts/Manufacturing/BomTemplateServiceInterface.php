<?php

declare(strict_types=1);

namespace App\Contracts\Manufacturing;

use App\Models\Manufacturing\BomTemplate;

/**
 * BOM Template service — core product/manual implementation, or brand-aware
 * ElectricalPanel add-on implementation when the feature flag is on.
 */
interface BomTemplateServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createTemplate(array $data): BomTemplate;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTemplate(BomTemplate $template, array $data): BomTemplate;

    public function deleteTemplate(BomTemplate $template): void;

    /**
     * Create a BOM from a template (core: product/manual only; panel: brand resolve).
     *
     * @param  array<string, mixed>  $options
     * @return array{bom: \App\Models\Manufacturing\Bom, report: array<string, mixed>}
     */
    public function createBomFromTemplate(BomTemplate $template, array $options): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, report: array<string, mixed>}
     */
    public function previewCreateFromTemplate(BomTemplate $template, array $options): array;

    /**
     * @return array<int, array{code: string, name: string, coverage: int, coverage_percent: float}>
     */
    public function getAvailableBrandsForTemplate(BomTemplate $template): array;
}
