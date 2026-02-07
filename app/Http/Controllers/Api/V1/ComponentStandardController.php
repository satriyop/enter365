<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreComponentStandardRequest;
use App\Http\Requests\Api\V1\UpdateComponentStandardRequest;
use App\Http\Resources\Api\V1\ComponentStandardResource;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Models\Manufacturing\ComponentStandard;
use App\Services\Manufacturing\ComponentStandardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComponentStandardController extends Controller
{
    public function __construct(
        private ComponentStandardService $standardService
    ) {}

    /**
     * Display a listing of component standards.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ComponentStandard::query()
            ->with(['brandMappings.product']);

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('subcategory')) {
            $query->where('subcategory', $request->input('subcategory'));
        }

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

        // Filter by specification values
        if ($request->has('specs')) {
            $specs = $request->input('specs');
            foreach ($specs as $key => $value) {
                $query->whereJsonContains("specifications->{$key}", $value);
            }
        }

        // Filter by brand availability
        if ($request->has('brand')) {
            $query->whereHas('brandMappings', function ($q) use ($request) {
                $q->where('brand', $request->input('brand'));
            });
        }

        $standards = $query->orderBy('category')
            ->orderBy('code')
            ->paginate($request->input('per_page', 25));

        return ComponentStandardResource::collection($standards);
    }

    /**
     * Store a newly created component standard.
     */
    public function store(StoreComponentStandardRequest $request): JsonResponse
    {
        $standard = $this->standardService->create($request->validated());

        return (new ComponentStandardResource($standard))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified component standard.
     */
    public function show(ComponentStandard $componentStandard): ComponentStandardResource
    {
        return new ComponentStandardResource(
            $componentStandard->load(['brandMappings.product', 'creator'])
        );
    }

    /**
     * Update the specified component standard.
     */
    public function update(
        UpdateComponentStandardRequest $request,
        ComponentStandard $componentStandard
    ): ComponentStandardResource {
        $standard = $this->standardService->update($componentStandard, $request->validated());

        return new ComponentStandardResource($standard);
    }

    /**
     * Remove the specified component standard.
     */
    public function destroy(ComponentStandard $componentStandard): JsonResponse
    {
        $this->standardService->delete($componentStandard);

        return response()->json(['message' => 'Component standard berhasil dihapus.']);
    }

    /**
     * Get categories with counts.
     */
    public function categories(): JsonResponse
    {
        $categories = ComponentStandard::query()
            ->active()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->toBase()
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'label' => ComponentStandard::getCategories()[$item->category] ?? $item->category,
                'count' => $item->count,
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * Get available brands for a standard.
     */
    public function brands(ComponentStandard $componentStandard): JsonResponse
    {
        $brands = $componentStandard->brandMappings()
            ->select('brand')
            ->distinct()
            ->pluck('brand')
            ->map(fn ($brand) => [
                'code' => $brand,
                'name' => ComponentBrandMapping::getBrands()[$brand] ?? ucfirst($brand),
            ]);

        return response()->json(['data' => $brands]);
    }
}
