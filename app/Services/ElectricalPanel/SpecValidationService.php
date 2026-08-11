<?php

namespace App\Services\ElectricalPanel;

use App\Models\Manufacturing\Bom;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Support\Features;
use Illuminate\Support\Collection;

/**
 * Electrical panel (Vahana) add-on: spec rules for brand-swap validation.
 * Soft-skips when FEATURE electrical_panel is off.
 */
class SpecValidationService
{
    /**
     * Validate a component swap against a rule set.
     *
     * @return array{valid: bool, warnings: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    public function validateSwap(
        ComponentStandard $standard,
        ComponentBrandMapping $originalMapping,
        ComponentBrandMapping $newMapping,
        ?SpecValidationRuleSet $ruleSet = null
    ): array {
        if (Features::disabled('electrical_panel')) {
            return ['valid' => true, 'warnings' => [], 'errors' => []];
        }

        $ruleSet = $ruleSet ?? SpecValidationRuleSet::getDefault();

        if (! $ruleSet) {
            return ['valid' => true, 'warnings' => [], 'errors' => []];
        }

        $rules = $ruleSet->getRulesForCategory($standard->category);

        if ($rules->isEmpty()) {
            return ['valid' => true, 'warnings' => [], 'errors' => []];
        }

        $warnings = [];
        $errors = [];

        $originalSpecs = $this->getMergedSpecs($standard, $originalMapping);
        $newSpecs = $this->getMergedSpecs($standard, $newMapping);

        foreach ($rules as $rule) {
            $originalValue = $originalSpecs[$rule->spec_key] ?? null;
            $newValue = $newSpecs[$rule->spec_key] ?? null;

            // Skip if both values are null
            if ($originalValue === null && $newValue === null) {
                continue;
            }

            $result = $rule->validate($originalValue, $newValue);

            if (! $result['valid']) {
                $issue = [
                    'spec_key' => $rule->spec_key,
                    'original_value' => $originalValue,
                    'new_value' => $newValue,
                    'message' => $result['message'],
                    'validation_type' => $rule->validation_type,
                ];

                if ($result['severity'] === 'error') {
                    $errors[] = $issue;
                } else {
                    $warnings[] = $issue;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Validate all items in a BOM when swapping to a target brand.
     *
     * @return array{valid: bool, items: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function validateBomBrandSwap(
        Bom $bom,
        string $targetBrand,
        ?SpecValidationRuleSet $ruleSet = null
    ): array {
        if (Features::disabled('electrical_panel')) {
            return [
                'valid' => true,
                'items' => [],
                'summary' => [
                    'total_items' => 0,
                    'validated_items' => 0,
                    'total_warnings' => 0,
                    'total_errors' => 0,
                ],
            ];
        }

        $ruleSet = $ruleSet ?? $bom->getEffectiveRuleSet();

        $items = [];
        $totalWarnings = 0;
        $totalErrors = 0;
        $valid = true;

        $bomItems = $bom->items()
            ->whereNotNull('component_standard_id')
            ->with(['componentStandard.brandMappings', 'product'])
            ->get();

        foreach ($bomItems as $bomItem) {
            $standard = $bomItem->componentStandard;

            if (! $standard) {
                continue;
            }

            // Find current mapping
            $currentMapping = $standard->brandMappings
                ->where('product_id', $bomItem->product_id)
                ->first();

            if (! $currentMapping) {
                continue;
            }

            // Find target mapping
            $targetMapping = $standard->getPreferredMapping($targetBrand);

            if (! $targetMapping) {
                $items[] = [
                    'bom_item_id' => $bomItem->id,
                    'product_id' => $bomItem->product_id,
                    'description' => $bomItem->description,
                    'status' => 'no_mapping',
                    'message' => "No mapping found for {$targetBrand}",
                    'warnings' => [],
                    'errors' => [],
                ];

                continue;
            }

            // Skip if same product
            if ($currentMapping->product_id === $targetMapping->product_id) {
                continue;
            }

            // Validate the swap
            $validation = $this->validateSwap($standard, $currentMapping, $targetMapping, $ruleSet);

            $items[] = [
                'bom_item_id' => $bomItem->id,
                'product_id' => $bomItem->product_id,
                'description' => $bomItem->description,
                'original_brand' => $currentMapping->brand,
                'target_brand' => $targetBrand,
                'target_product_id' => $targetMapping->product_id,
                'target_product_name' => $targetMapping->product?->name,
                'status' => $validation['valid'] ? 'valid' : 'issues',
                'warnings' => $validation['warnings'],
                'errors' => $validation['errors'],
            ];

            $totalWarnings += count($validation['warnings']);
            $totalErrors += count($validation['errors']);

            if (! $validation['valid']) {
                $valid = false;
            }
        }

        return [
            'valid' => $valid,
            'items' => $items,
            'summary' => [
                'total_items' => count($bomItems),
                'validated_items' => count($items),
                'total_warnings' => $totalWarnings,
                'total_errors' => $totalErrors,
            ],
        ];
    }

    /**
     * Get merged specifications from standard and mapping's variant specs.
     *
     * @return array<string, mixed>
     */
    public function getMergedSpecs(ComponentStandard $standard, ComponentBrandMapping $mapping): array
    {
        $baseSpecs = $standard->specifications ?? [];
        $variantSpecs = $mapping->variant_specs ?? [];

        return array_merge($baseSpecs, $variantSpecs);
    }

    /**
     * Compare specs between two mappings.
     *
     * @return array<string, array{original: mixed, new: mixed, status: string}>
     */
    public function compareSpecs(
        ComponentStandard $standard,
        ComponentBrandMapping $originalMapping,
        ComponentBrandMapping $newMapping
    ): array {
        $originalSpecs = $this->getMergedSpecs($standard, $originalMapping);
        $newSpecs = $this->getMergedSpecs($standard, $newMapping);

        $allKeys = array_unique(array_merge(
            array_keys($originalSpecs),
            array_keys($newSpecs)
        ));

        $comparison = [];

        foreach ($allKeys as $key) {
            $originalValue = $originalSpecs[$key] ?? null;
            $newValue = $newSpecs[$key] ?? null;

            $status = 'unchanged';
            if ($originalValue === null && $newValue !== null) {
                $status = 'added';
            } elseif ($originalValue !== null && $newValue === null) {
                $status = 'removed';
            } elseif ($originalValue !== $newValue) {
                // Numeric comparison
                if (is_numeric($originalValue) && is_numeric($newValue)) {
                    $status = (float) $newValue > (float) $originalValue ? 'increased' : 'decreased';
                } else {
                    $status = 'changed';
                }
            }

            $comparison[$key] = [
                'original' => $originalValue,
                'new' => $newValue,
                'status' => $status,
            ];
        }

        return $comparison;
    }

    /**
     * Get available spec keys for a category based on existing standards.
     *
     * @return Collection<int, string>
     */
    public function getAvailableSpecKeys(string $category): Collection
    {
        return ComponentStandard::where('category', $category)
            ->whereNotNull('specifications')
            ->get()
            ->flatMap(fn ($standard) => array_keys($standard->specifications ?? []))
            ->unique()
            ->values();
    }

    /**
     * Validate all current BOM items against the rule set (without swap).
     * Useful for checking if current components meet the rule requirements.
     *
     * @return array{valid: bool, items: array<int, array<string, mixed>>}
     */
    public function validateBomCompliance(Bom $bom, ?SpecValidationRuleSet $ruleSet = null): array
    {
        if (Features::disabled('electrical_panel')) {
            return ['valid' => true, 'items' => []];
        }

        $ruleSet = $ruleSet ?? $bom->getEffectiveRuleSet();

        if (! $ruleSet) {
            return ['valid' => true, 'items' => []];
        }

        $items = [];
        $valid = true;

        $bomItems = $bom->items()
            ->whereNotNull('component_standard_id')
            ->with(['componentStandard.brandMappings', 'product'])
            ->get();

        foreach ($bomItems as $bomItem) {
            $standard = $bomItem->componentStandard;

            if (! $standard) {
                continue;
            }

            $rules = $ruleSet->getRulesForCategory($standard->category);

            if ($rules->isEmpty()) {
                continue;
            }

            $mapping = $standard->brandMappings
                ->where('product_id', $bomItem->product_id)
                ->first();

            if (! $mapping) {
                continue;
            }

            $specs = $this->getMergedSpecs($standard, $mapping);
            $issues = [];

            foreach ($rules as $rule) {
                $value = $specs[$rule->spec_key] ?? null;

                // Only check min_value and max_value rules for compliance
                if ($rule->validation_type === 'min_value' && $value !== null && $rule->threshold_value !== null) {
                    if ((float) $value < (float) $rule->threshold_value) {
                        $issues[] = [
                            'spec_key' => $rule->spec_key,
                            'current_value' => $value,
                            'required_minimum' => $rule->threshold_value,
                            'message' => $rule->message ?? "Value {$value} is below minimum {$rule->threshold_value}",
                            'severity' => $rule->severity,
                        ];
                    }
                }

                if ($rule->validation_type === 'max_value' && $value !== null && $rule->threshold_value !== null) {
                    if ((float) $value > (float) $rule->threshold_value) {
                        $issues[] = [
                            'spec_key' => $rule->spec_key,
                            'current_value' => $value,
                            'required_maximum' => $rule->threshold_value,
                            'message' => $rule->message ?? "Value {$value} exceeds maximum {$rule->threshold_value}",
                            'severity' => $rule->severity,
                        ];
                    }
                }
            }

            if (! empty($issues)) {
                $items[] = [
                    'bom_item_id' => $bomItem->id,
                    'product_id' => $bomItem->product_id,
                    'description' => $bomItem->description,
                    'issues' => $issues,
                ];

                $hasErrors = collect($issues)->contains('severity', 'error');
                if ($hasErrors) {
                    $valid = false;
                }
            }
        }

        return [
            'valid' => $valid,
            'items' => $items,
        ];
    }
}
