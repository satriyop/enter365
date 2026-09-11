<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AnalyticDimensionServiceInterface;
use App\Http\Requests\Api\V1\StoreAnalyticDistributionModelRequest;
use App\Http\Requests\Api\V1\UpdateAnalyticDistributionModelRequest;
use App\Http\Resources\Api\V1\AnalyticDistributionModelResource;
use App\Models\Accounting\AnalyticDistributionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnalyticDistributionModelController extends Controller
{
    public function __construct(
        private AnalyticDimensionServiceInterface $analytics,
    ) {}

    /**
     * List analytic distribution models (Odoo Configuration › Analytic Distribution Models).
     *
     * @queryParam search string Search by name. Example: Marketing
     * @queryParam is_active bool Filter by active status. Example: 1
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnalyticDistributionModel::class);

        $query = AnalyticDistributionModel::query()
            ->with(['partner', 'product'])
            ->orderBy('sequence')
            ->orderBy('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return AnalyticDistributionModelResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * Return the first matching distribution for partner / account / product.
     *
     * @queryParam partner_id int Contact id. Example: 12
     * @queryParam account_id int GL account id. Example: 5
     * @queryParam product_id int Product id. Example: 3
     */
    public function match(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AnalyticDistributionModel::class);

        $distribution = $this->analytics->matchDistribution(
            $request->integer('partner_id') ?: null,
            $request->integer('account_id') ?: null,
            $request->integer('product_id') ?: null,
        );

        return $this->success([
            'analytic_distribution' => $distribution,
        ]);
    }

    public function store(StoreAnalyticDistributionModelRequest $request): JsonResponse
    {
        $this->authorize('create', AnalyticDistributionModel::class);

        $model = $this->analytics->createDistributionModel($request->validated());

        return (new AnalyticDistributionModelResource($model->load(['partner', 'product'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AnalyticDistributionModel $analyticDistributionModel): AnalyticDistributionModelResource
    {
        $this->authorize('view', $analyticDistributionModel);

        return new AnalyticDistributionModelResource($analyticDistributionModel->load(['partner', 'product']));
    }

    public function update(
        UpdateAnalyticDistributionModelRequest $request,
        AnalyticDistributionModel $analyticDistributionModel,
    ): AnalyticDistributionModelResource {
        $this->authorize('update', $analyticDistributionModel);

        return new AnalyticDistributionModelResource(
            $this->analytics->updateDistributionModel($analyticDistributionModel, $request->validated())
        );
    }

    public function destroy(AnalyticDistributionModel $analyticDistributionModel): JsonResponse
    {
        $this->authorize('delete', $analyticDistributionModel);

        $this->analytics->deleteDistributionModel($analyticDistributionModel);

        return $this->deleted('Model distribusi analitik berhasil dihapus.');
    }
}
