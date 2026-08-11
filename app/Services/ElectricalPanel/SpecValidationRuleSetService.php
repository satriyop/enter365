<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Models\ElectricalPanel\SpecValidationRuleSet;

class SpecValidationRuleSetService
{
    /**
     * Create a new spec validation rule set.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SpecValidationRuleSet
    {
        $ruleSet = SpecValidationRuleSet::create($data);

        // If this is set as default, unset other defaults
        if ($ruleSet->is_default) {
            $ruleSet->setAsDefault();
        }

        return $ruleSet->load('rules');
    }

    /**
     * Update a spec validation rule set.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SpecValidationRuleSet $ruleSet, array $data): SpecValidationRuleSet
    {
        $ruleSet->update($data);

        // If this is set as default, unset other defaults
        if (isset($data['is_default']) && $data['is_default']) {
            $ruleSet->setAsDefault();
        }

        return $ruleSet->fresh(['rules', 'creator']);
    }

    /**
     * Delete a spec validation rule set.
     *
     * Validates that no BOMs reference this rule set before deletion.
     *
     * @throws \App\Exceptions\Domain\ValidationException
     */
    public function delete(SpecValidationRuleSet $ruleSet): void
    {
        // Check if any BOMs reference this rule set
        $bomCount = $ruleSet->boms()->count();

        if ($bomCount > 0) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'rule_set',
                "Tidak dapat dihapus: {$bomCount} BOM menggunakan rule set ini."
            );
        }

        $ruleSet->delete();
    }

    /**
     * Set a rule set as default.
     */
    public function setAsDefault(SpecValidationRuleSet $ruleSet): SpecValidationRuleSet
    {
        $ruleSet->setAsDefault();

        return $ruleSet->fresh('rules');
    }
}
