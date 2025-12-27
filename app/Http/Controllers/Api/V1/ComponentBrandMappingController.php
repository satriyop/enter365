<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreComponentBrandMappingRequest;
use App\Http\Requests\Api\V1\UpdateComponentBrandMappingRequest;
use App\Http\Resources\Api\V1\ComponentBrandMappingResource;
use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\ComponentStandard;
use Illuminate\Http\JsonResponse;

class ComponentBrandMappingController extends Controller
{
    /**
     * Add a brand mapping to a component standard.
     */
    public function store(
        StoreComponentBrandMappingRequest $request,
        ComponentStandard $componentStandard
    ): JsonResponse {
        $data = $request->validated();
        $data['component_standard_id'] = $componentStandard->id;

        $mapping = ComponentBrandMapping::create($data);

        // Set as preferred if requested
        if ($request->boolean('is_preferred')) {
            $mapping->setAsPreferred();
        }

        return (new ComponentBrandMappingResource($mapping->load('product')))
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

        $mapping->update($request->validated());

        if ($request->boolean('is_preferred')) {
            $mapping->setAsPreferred();
        }

        return new ComponentBrandMappingResource($mapping->fresh('product'));
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

        $mapping->delete();

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

        $mapping->verify(auth()->id());

        return response()->json([
            'message' => 'Brand mapping berhasil diverifikasi.',
            'data' => new ComponentBrandMappingResource($mapping->fresh('product')),
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

        $mapping->setAsPreferred();

        return response()->json([
            'message' => 'Brand mapping berhasil di-set sebagai preferred.',
            'data' => new ComponentBrandMappingResource($mapping->fresh('product')),
        ]);
    }
}
