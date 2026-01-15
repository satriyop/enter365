<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Manufacturing\BomTemplate;

/**
 * Interface for BOM Template service operations.
 *
 * Focused on creating BOMs from templates with brand substitution.
 */
interface BomTemplateServiceInterface
{
    /**
     * Create a BOM from a template with brand substitution options.
     *
     * @param  array<string, mixed>  $options  Options including target brand, create variant, etc.
     * @return array<string, mixed>  Result with created BOM and swap report
     */
    public function createBomFromTemplate(BomTemplate $template, array $options): array;

    /**
     * Preview what a BOM would look like when created from template.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function previewCreateFromTemplate(BomTemplate $template, array $options): array;

    /**
     * Get available brands for component substitution in a template.
     *
     * @return array<string>
     */
    public function getAvailableBrandsForTemplate(BomTemplate $template): array;
}
