<?php

namespace App\Models\ElectricalPanel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecValidationRule extends Model
{
    use HasFactory;

    // Validation Types
    public const TYPE_WARN_IF_LOWER = 'warn_if_lower';

    public const TYPE_WARN_IF_HIGHER = 'warn_if_higher';

    public const TYPE_MUST_MATCH = 'must_match';

    public const TYPE_MIN_VALUE = 'min_value';

    public const TYPE_MAX_VALUE = 'max_value';

    // Severity Levels
    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    protected $fillable = [
        'rule_set_id',
        'category',
        'spec_key',
        'validation_type',
        'threshold_value',
        'severity',
        'message',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SpecValidationRuleSet, $this>
     */
    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(SpecValidationRuleSet::class, 'rule_set_id');
    }

    /**
     * Validate a spec value against this rule.
     *
     * @return array{valid: bool, message: string|null, severity: string|null}
     */
    public function validate(mixed $originalValue, mixed $newValue): array
    {
        $result = ['valid' => true, 'message' => null, 'severity' => null];

        // Convert to numeric for comparison if possible
        $originalNumeric = is_numeric($originalValue) ? (float) $originalValue : null;
        $newNumeric = is_numeric($newValue) ? (float) $newValue : null;

        switch ($this->validation_type) {
            case self::TYPE_WARN_IF_LOWER:
                if ($originalNumeric !== null && $newNumeric !== null && $newNumeric < $originalNumeric) {
                    $result = $this->buildValidationResult(
                        "Value decreased from {$originalValue} to {$newValue}"
                    );
                }
                break;

            case self::TYPE_WARN_IF_HIGHER:
                if ($originalNumeric !== null && $newNumeric !== null && $newNumeric > $originalNumeric) {
                    $result = $this->buildValidationResult(
                        "Value increased from {$originalValue} to {$newValue}"
                    );
                }
                break;

            case self::TYPE_MUST_MATCH:
                if ($originalValue != $newValue) {
                    $result = $this->buildValidationResult(
                        "Values must match: {$originalValue} vs {$newValue}"
                    );
                }
                break;

            case self::TYPE_MIN_VALUE:
                if ($newNumeric !== null && $this->threshold_value !== null && $newNumeric < (float) $this->threshold_value) {
                    $result = $this->buildValidationResult(
                        "Value {$newValue} is below minimum {$this->threshold_value}"
                    );
                }
                break;

            case self::TYPE_MAX_VALUE:
                if ($newNumeric !== null && $this->threshold_value !== null && $newNumeric > (float) $this->threshold_value) {
                    $result = $this->buildValidationResult(
                        "Value {$newValue} exceeds maximum {$this->threshold_value}"
                    );
                }
                break;
        }

        return $result;
    }

    /**
     * Build validation result array.
     *
     * @return array{valid: bool, message: string, severity: string}
     */
    private function buildValidationResult(string $defaultMessage): array
    {
        return [
            'valid' => false,
            'message' => $this->message ?? $defaultMessage,
            'severity' => $this->severity,
        ];
    }

    /**
     * Get validation types list.
     *
     * @return array<string, string>
     */
    public static function getValidationTypes(): array
    {
        return [
            self::TYPE_WARN_IF_LOWER => 'Warn if Lower',
            self::TYPE_WARN_IF_HIGHER => 'Warn if Higher',
            self::TYPE_MUST_MATCH => 'Must Match',
            self::TYPE_MIN_VALUE => 'Minimum Value',
            self::TYPE_MAX_VALUE => 'Maximum Value',
        ];
    }

    /**
     * Get severity levels list.
     *
     * @return array<string, string>
     */
    public static function getSeverityLevels(): array
    {
        return [
            self::SEVERITY_WARNING => 'Warning',
            self::SEVERITY_ERROR => 'Error',
        ];
    }

    /**
     * Check if this rule requires a threshold value.
     */
    public function requiresThreshold(): bool
    {
        return in_array($this->validation_type, [self::TYPE_MIN_VALUE, self::TYPE_MAX_VALUE]);
    }

    /**
     * Get a human-readable description of this rule.
     */
    public function getDescription(): string
    {
        $types = self::getValidationTypes();
        $typeLabel = $types[$this->validation_type] ?? $this->validation_type;

        if ($this->requiresThreshold() && $this->threshold_value !== null) {
            return "{$this->spec_key}: {$typeLabel} ({$this->threshold_value})";
        }

        return "{$this->spec_key}: {$typeLabel}";
    }
}
