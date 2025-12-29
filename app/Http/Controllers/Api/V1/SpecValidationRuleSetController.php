<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSpecValidationRuleRequest;
use App\Http\Requests\Api\V1\StoreSpecValidationRuleSetRequest;
use App\Http\Requests\Api\V1\UpdateSpecValidationRuleRequest;
use App\Http\Requests\Api\V1\UpdateSpecValidationRuleSetRequest;
use App\Http\Resources\Api\V1\SpecValidationRuleResource;
use App\Http\Resources\Api\V1\SpecValidationRuleSetResource;
use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\SpecValidationRule;
use App\Models\Accounting\SpecValidationRuleSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpecValidationRuleSetController extends Controller
{
    /**
     * List all rule sets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SpecValidationRuleSet::query()
            ->with(['rules', 'creator']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
            });
        }

        $ruleSets = $query->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate($request->input('per_page', 25));

        return SpecValidationRuleSetResource::collection($ruleSets);
    }

    /**
     * Create a new rule set.
     */
    public function store(StoreSpecValidationRuleSetRequest $request): JsonResponse
    {
        $ruleSet = SpecValidationRuleSet::create($request->validated());

        // If this is set as default, unset other defaults
        if ($ruleSet->is_default) {
            $ruleSet->setAsDefault();
        }

        return (new SpecValidationRuleSetResource($ruleSet->load('rules')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a rule set with its rules.
     */
    public function show(SpecValidationRuleSet $specRuleSet): SpecValidationRuleSetResource
    {
        return new SpecValidationRuleSetResource(
            $specRuleSet->load(['rules', 'creator'])
        );
    }

    /**
     * Update a rule set.
     */
    public function update(
        UpdateSpecValidationRuleSetRequest $request,
        SpecValidationRuleSet $specRuleSet
    ): SpecValidationRuleSetResource {
        $specRuleSet->update($request->validated());

        // If this is set as default, unset other defaults
        if ($request->boolean('is_default')) {
            $specRuleSet->setAsDefault();
        }

        return new SpecValidationRuleSetResource(
            $specRuleSet->fresh(['rules', 'creator'])
        );
    }

    /**
     * Delete a rule set.
     */
    public function destroy(SpecValidationRuleSet $specRuleSet): JsonResponse
    {
        // Check if any BOMs reference this rule set
        $bomCount = $specRuleSet->boms()->count();

        if ($bomCount > 0) {
            return response()->json([
                'message' => "Tidak dapat dihapus: {$bomCount} BOM menggunakan rule set ini.",
            ], 422);
        }

        $specRuleSet->delete();

        return response()->json(['message' => 'Rule set berhasil dihapus.']);
    }

    /**
     * Set a rule set as default.
     */
    public function setDefault(SpecValidationRuleSet $specRuleSet): JsonResponse
    {
        $specRuleSet->setAsDefault();

        return response()->json([
            'message' => 'Rule set berhasil dijadikan default.',
            'data' => new SpecValidationRuleSetResource($specRuleSet->fresh('rules')),
        ]);
    }

    /**
     * Get metadata for creating rules.
     */
    public function metadata(): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => ComponentStandard::getCategories(),
                'validation_types' => SpecValidationRule::getValidationTypes(),
                'severity_levels' => SpecValidationRule::getSeverityLevels(),
                'common_spec_keys' => $this->getCommonSpecKeys(),
            ],
        ]);
    }

    /**
     * Add a rule to a rule set.
     */
    public function storeRule(
        StoreSpecValidationRuleRequest $request,
        SpecValidationRuleSet $specRuleSet
    ): JsonResponse {
        $rule = $specRuleSet->rules()->create($request->validated());

        return (new SpecValidationRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a rule within a rule set.
     */
    public function updateRule(
        UpdateSpecValidationRuleRequest $request,
        SpecValidationRuleSet $specRuleSet,
        SpecValidationRule $rule
    ): SpecValidationRuleResource {
        // Ensure the rule belongs to the rule set
        if ($rule->rule_set_id !== $specRuleSet->id) {
            abort(404, 'Rule tidak ditemukan dalam rule set ini.');
        }

        $rule->update($request->validated());

        return new SpecValidationRuleResource($rule->fresh());
    }

    /**
     * Delete a rule from a rule set.
     */
    public function destroyRule(
        SpecValidationRuleSet $specRuleSet,
        SpecValidationRule $rule
    ): JsonResponse {
        // Ensure the rule belongs to the rule set
        if ($rule->rule_set_id !== $specRuleSet->id) {
            abort(404, 'Rule tidak ditemukan dalam rule set ini.');
        }

        $rule->delete();

        return response()->json(['message' => 'Rule berhasil dihapus.']);
    }

    /**
     * Reorder rules within a rule set.
     */
    public function reorderRules(
        Request $request,
        SpecValidationRuleSet $specRuleSet
    ): JsonResponse {
        $request->validate([
            'rule_ids' => ['required', 'array'],
            'rule_ids.*' => ['required', 'integer', 'exists:spec_validation_rules,id'],
        ]);

        foreach ($request->input('rule_ids') as $index => $ruleId) {
            SpecValidationRule::where('id', $ruleId)
                ->where('rule_set_id', $specRuleSet->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Urutan rule berhasil diubah.',
            'data' => new SpecValidationRuleSetResource($specRuleSet->fresh('rules')),
        ]);
    }

    /**
     * Get common spec keys by category for UI autocomplete.
     *
     * @return array<string, array<string>>
     */
    private function getCommonSpecKeys(): array
    {
        return [
            ComponentStandard::CATEGORY_CIRCUIT_BREAKER => [
                'rating_amps',
                'poles',
                'curve',
                'breaking_capacity_ka',
                'voltage_rating',
            ],
            ComponentStandard::CATEGORY_CONTACTOR => [
                'rating_amps',
                'coil_voltage',
                'poles',
                'ac_category',
            ],
            ComponentStandard::CATEGORY_CABLE => [
                'conductor_size_mm2',
                'cores',
                'insulation',
                'voltage_rating',
            ],
            ComponentStandard::CATEGORY_BUSBAR => [
                'material',
                'width_mm',
                'thickness_mm',
                'current_rating_a',
            ],
            ComponentStandard::CATEGORY_RELAY => [
                'coil_voltage',
                'contact_configuration',
                'current_rating',
            ],
            ComponentStandard::CATEGORY_ENCLOSURE => [
                'ip_rating',
                'material',
                'dimensions',
            ],
        ];
    }
}
