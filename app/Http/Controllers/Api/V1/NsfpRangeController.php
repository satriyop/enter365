<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Tax\NsfpServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNsfpRangeRequest;
use App\Http\Requests\Api\V1\UpdateNsfpRangeRequest;
use App\Http\Resources\Api\V1\NsfpRangeResource;
use App\Models\Tax\NsfpRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NsfpRangeController extends Controller
{
    public function __construct(
        private NsfpServiceInterface $nsfpService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NsfpRange::class);

        $query = NsfpRange::query()
            ->with('createdBy')
            ->withCount('invoices');

        if ($request->filled('year_code')) {
            $query->forYear($request->input('year_code'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $ranges = $query
            ->orderByDesc('is_active')
            ->orderByDesc('year_code')
            ->orderBy('range_start')
            ->paginate($request->input('per_page', 25));

        return NsfpRangeResource::collection($ranges);
    }

    public function store(StoreNsfpRangeRequest $request): JsonResponse
    {
        $this->authorize('create', NsfpRange::class);

        $range = $this->nsfpService->createRange(
            $request->validated(),
            $request->user()?->id
        );

        return (new NsfpRangeResource($range))
            ->response()
            ->setStatusCode(201);
    }

    public function show(NsfpRange $nsfpRange): NsfpRangeResource
    {
        $this->authorize('view', $nsfpRange);

        $nsfpRange->load('createdBy');
        $nsfpRange->loadCount('invoices');

        return new NsfpRangeResource($nsfpRange);
    }

    public function update(UpdateNsfpRangeRequest $request, NsfpRange $nsfpRange): NsfpRangeResource
    {
        $this->authorize('update', $nsfpRange);

        $range = $this->nsfpService->updateRange($nsfpRange, $request->validated());

        return new NsfpRangeResource($range);
    }

    public function deactivate(NsfpRange $nsfpRange): JsonResponse
    {
        $this->authorize('deactivate', $nsfpRange);

        $range = $this->nsfpService->deactivateRange($nsfpRange);

        return response()->json([
            'message' => 'Range NSFP berhasil dinonaktifkan.',
            'data' => new NsfpRangeResource($range),
        ]);
    }

    /**
     * Get utilization summary across all active ranges.
     */
    public function utilization(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NsfpRange::class);

        $query = NsfpRange::query();

        if ($request->filled('year_code')) {
            $query->forYear($request->input('year_code'));
        }

        $ranges = $query->orderByDesc('year_code')->orderBy('range_start')->get();

        $active = $ranges->where('is_active', true);
        $totalCapacity = $ranges->sum(fn (NsfpRange $r) => $r->getCapacity());
        $totalUsed = $ranges->sum('used_count');
        $totalRemaining = $active->sum(fn (NsfpRange $r) => $r->getRemainingCount());

        return response()->json([
            'summary' => [
                'total_ranges' => $ranges->count(),
                'active_ranges' => $active->count(),
                'total_capacity' => $totalCapacity,
                'total_used' => $totalUsed,
                'total_remaining' => $totalRemaining,
                'utilization_percent' => $totalCapacity > 0
                    ? round(($totalUsed / $totalCapacity) * 100, 1)
                    : 0,
            ],
            'ranges' => NsfpRangeResource::collection($ranges),
        ]);
    }
}
