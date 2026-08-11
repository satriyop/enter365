<?php

namespace App\Http\Controllers\Api\V1\ElectricalPanel;

use App\Http\Controllers\Api\V1\Controller;
use App\Http\Requests\Api\V1\ElectricalPanel\StoreComponentBrandMappingRequest;
use App\Http\Requests\Api\V1\ElectricalPanel\UpdateComponentBrandMappingRequest;
use App\Http\Resources\Api\V1\ElectricalPanel\ComponentBrandMappingResource;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Services\ElectricalPanel\ComponentBrandMappingService;
use Illuminate\Http\JsonResponse;

class ComponentBrandMappingController extends Controller
{
    public function __construct(
        private ComponentBrandMappingService $mappingService
    ) {}

    /**
     * Add a brand mapping to a component standard.
     */
    public function store(
        StoreComponentBrandMappingRequest $request,
        ComponentStandard $componentStandard
    ): JsonResponse {
        $mapping = $this->mappingService->create($componentStandard, $request->validated());

        return (new ComponentBrandMappingResource($mapping))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a brand mapping.
     */
    public function update(
        UpdateComponentBrandMappingRequest $request,
        ComponentStandard $componentStandard,
        ComponentBrandMapping $mapping
    ): ComponentBrandMappingResource {
        if ($mapping->component_standard_id !== $componentStandard->id) {
            abort(404, 'Mapping tidak ditemukan untuk standard ini.');
        }

        $mapping = $this->mappingService->update($mapping, $request->validated());

        return new ComponentBrandMappingResource($mapping);
    }

    /**
     * Remove a brand mapping.
     */
    public function destroy(
        ComponentStandard $componentStandard,
        ComponentBrandMapping $mapping
    ): JsonResponse {
        if ($mapping->component_standard_id !== $componentStandard->id) {
            abort(404, 'Mapping tidak ditemukan untuk standard ini.');
        }

        $this->mappingService->delete($mapping);

        return response()->json(['message' => 'Brand mapping berhasil dihapus.']);
    }

    /**
     * Verify a brand mapping.
     */
    public function verify(
        ComponentStandard $componentStandard,
        ComponentBrandMapping $mapping
    ): JsonResponse {
        if ($mapping->component_standard_id !== $componentStandard->id) {
            abort(404, 'Mapping tidak ditemukan untuk standard ini.');
        }

        $mapping = $this->mappingService->verify($mapping, auth()->id());

        return response()->json([
            'message' => 'Brand mapping berhasil diverifikasi.',
            'data' => new ComponentBrandMappingResource($mapping),
        ]);
    }

    /**
     * Set mapping as preferred.
     */
    public function setPreferred(
        ComponentStandard $componentStandard,
        ComponentBrandMapping $mapping
    ): JsonResponse {
        if ($mapping->component_standard_id !== $componentStandard->id) {
            abort(404, 'Mapping tidak ditemukan untuk standard ini.');
        }

        $mapping = $this->mappingService->setAsPreferred($mapping);

        return response()->json([
            'message' => 'Brand mapping berhasil di-set sebagai preferred.',
            'data' => new ComponentBrandMappingResource($mapping),
        ]);
    }
}
