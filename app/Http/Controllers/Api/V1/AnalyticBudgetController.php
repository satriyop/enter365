<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AnalyticDimensionServiceInterface;
use App\Http\Requests\Api\V1\StoreAnalyticBudgetRequest;
use App\Http\Requests\Api\V1\UpdateAnalyticBudgetRequest;
use App\Http\Resources\Api\V1\AnalyticBudgetResource;
use App\Models\Accounting\AnalyticBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnalyticBudgetController extends Controller
{
    public function __construct(
        private AnalyticDimensionServiceInterface $analytics,
    ) {}

    /**
     * List analytic budgets (Odoo Accounting › Analytic Budgets).
     *
     * @queryParam search string Search by name. Example: Q1
     * @queryParam status string Filter by draft, open, or closed. Example: open
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnalyticBudget::class);

        $query = AnalyticBudget::query()->withCount('lines')->orderByDesc('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return AnalyticBudgetResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreAnalyticBudgetRequest $request): JsonResponse
    {
        $this->authorize('create', AnalyticBudget::class);

        $budget = $this->analytics->createBudget($request->validated());

        return (new AnalyticBudgetResource($this->analytics->withActuals($budget)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AnalyticBudget $analyticBudget): AnalyticBudgetResource
    {
        $this->authorize('view', $analyticBudget);

        return new AnalyticBudgetResource($this->analytics->withActuals($analyticBudget));
    }

    public function update(UpdateAnalyticBudgetRequest $request, AnalyticBudget $analyticBudget): AnalyticBudgetResource
    {
        $this->authorize('update', $analyticBudget);

        $budget = $this->analytics->updateBudget($analyticBudget, $request->validated());

        return new AnalyticBudgetResource($this->analytics->withActuals($budget));
    }

    public function destroy(AnalyticBudget $analyticBudget): JsonResponse
    {
        $this->authorize('delete', $analyticBudget);

        $this->analytics->deleteBudget($analyticBudget);

        return $this->deleted('Anggaran analitik berhasil dihapus.');
    }
}
