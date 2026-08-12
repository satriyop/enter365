<?php

declare(strict_types=1);

namespace App\Contracts\Manufacturing;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;

/**
 * BOM Template service — core product/manual lines.
 *
 * Optional product add-ons may rebind this interface and extend payloads via AddonExtensions.
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
     * @param  array{code: string, name?: string|null, thumbnail_path?: string|null}  $options
     */
    public function duplicateTemplate(BomTemplate $template, array $options): BomTemplate;

    /**
     * Apply optional add-on attributes for a template item (core is a no-op).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyItemAddonAttributes(BomTemplateItem $item, array $attributes): void;

    /**
     * Create a BOM from a template (product/manual lines; add-ons may resolve extra options).
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
}
