<?php

declare(strict_types=1);

namespace App\Contracts\Manufacturing;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;

/**
 * Electrical-panel add-on extension for BOM template brand resolution.
 *
 * Core Manufacturing depends only on this contract (null impl when add-on off).
 */
interface BomTemplateBrandResolverInterface
{
    public function isEnabled(): bool;

    /**
     * Resolve a template line that has no direct product_id (standard-based or fallback).
     * Return null to let core treat the line as a plain manual description.
     *
     * @return array{status: string, bom_item_data: array<string, mixed>, product: ?array<string, mixed>, notes: ?string}|null
     */
    public function resolveStandardBasedItem(
        BomTemplateItem $templateItem,
        ?string $targetBrand,
        float $quantity
    ): ?array;

    /**
     * Whether component_standard_id should be copied onto BOM items from product lines.
     */
    public function shouldPersistStandardId(): bool;

    /**
     * Eager-load relations needed for brand resolution on a template.
     *
     * @return list<string>
     */
    public function templateEagerLoads(): array;

    /**
     * Preview metadata for a template item's component standard (or null when off).
     *
     * @return array{id: int, code: string, name: string}|null
     */
    public function standardPreview(BomTemplateItem $templateItem): ?array;

    /**
     * @return array<int, array{code: string, name: string, coverage: int, coverage_percent: float}>
     */
    public function availableBrandsForTemplate(BomTemplate $template): array;

    /**
     * Spec rule set id to stamp on BOMs created from the template (null when off).
     */
    public function templateSpecRuleSetId(BomTemplate $template): ?int;
}
