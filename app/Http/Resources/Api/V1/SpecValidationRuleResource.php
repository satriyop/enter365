<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Manufacturing\ComponentStandard;
use App\Models\Manufacturing\SpecValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\SpecValidationRule
 */
class SpecValidationRuleResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   rule_set_id: int,
     *   category: string,
     *   category_label: string,
     *   spec_key: string,
     *   validation_type: string,
     *   validation_type_label: string,
     *   threshold_value: float|null,
     *   severity: string,
     *   severity_label: string,
     *   message: string,
     *   sort_order: int,
     *   description: string,
     *   requires_threshold: bool,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_set_id' => $this->rule_set_id,
            'category' => $this->category,
            'category_label' => ComponentStandard::getCategories()[$this->category] ?? $this->category,
            'spec_key' => $this->spec_key,
            'validation_type' => $this->validation_type,
            'validation_type_label' => SpecValidationRule::getValidationTypes()[$this->validation_type] ?? $this->validation_type,
            'threshold_value' => $this->threshold_value,
            'severity' => $this->severity,
            'severity_label' => SpecValidationRule::getSeverityLevels()[$this->severity] ?? $this->severity,
            'message' => $this->message,
            'sort_order' => $this->sort_order,
            'description' => $this->getDescription(),
            'requires_threshold' => $this->requiresThreshold(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
