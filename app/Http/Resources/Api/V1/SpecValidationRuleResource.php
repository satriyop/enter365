<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\SpecValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpecValidationRule
 */
class SpecValidationRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
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
