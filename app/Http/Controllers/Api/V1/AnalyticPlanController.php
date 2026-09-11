<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AnalyticDimensionServiceInterface;
use App\Http\Requests\Api\V1\StoreAnalyticPlanRequest;
use App\Http\Requests\Api\V1\UpdateAnalyticPlanRequest;
use App\Http\Resources\Api\V1\AnalyticPlanResource;
use App\Models\Accounting\AnalyticPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnalyticPlanController extends Controller
{
    public function __construct(
        private AnalyticDimensionServiceInterface $analytics,
    ) {}

    /**
     * List analytic plans (Odoo Configuration › Analytic Plans).
     *
     * @queryParam search string Search by code or name. Example: DEPT
     * @queryParam is_active bool Filter by active status. Example: 1
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnalyticPlan::class);

        $query = AnalyticPlan::query()->with('parent')->withCount('analyticAccounts')->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return AnalyticPlanResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreAnalyticPlanRequest $request): JsonResponse
    {
        $this->authorize('create', AnalyticPlan::class);

        $plan = $this->analytics->createPlan($request->validated());

        return (new AnalyticPlanResource($plan->load('parent')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AnalyticPlan $analyticPlan): AnalyticPlanResource
    {
        $this->authorize('view', $analyticPlan);

        return new AnalyticPlanResource($analyticPlan->load(['parent', 'analyticAccounts']));
    }

    public function update(UpdateAnalyticPlanRequest $request, AnalyticPlan $analyticPlan): AnalyticPlanResource
    {
        $this->authorize('update', $analyticPlan);

        return new AnalyticPlanResource($this->analytics->updatePlan($analyticPlan, $request->validated()));
    }

    public function destroy(AnalyticPlan $analyticPlan): JsonResponse
    {
        $this->authorize('delete', $analyticPlan);

        $this->analytics->deletePlan($analyticPlan);

        return $this->deleted('Rencana analitik berhasil dihapus.');
    }
}
