<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAnalyticAccountRequest;
use App\Http\Requests\Api\V1\UpdateAnalyticAccountRequest;
use App\Http\Resources\Api\V1\AnalyticAccountResource;
use App\Models\Accounting\AnalyticAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnalyticAccountController extends Controller
{
    /**
     * List analytic accounts.
     *
     * @queryParam search string Search by code or name. Example: PROJ
     * @queryParam is_active bool Filter by active status. Example: 1
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnalyticAccount::class);

        $query = AnalyticAccount::query()->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return AnalyticAccountResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * Create an analytic account.
     */
    public function store(StoreAnalyticAccountRequest $request): JsonResponse
    {
        $this->authorize('create', AnalyticAccount::class);

        $account = AnalyticAccount::query()->create($request->validated());

        return (new AnalyticAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an analytic account.
     */
    public function show(AnalyticAccount $analyticAccount): AnalyticAccountResource
    {
        $this->authorize('view', $analyticAccount);

        return new AnalyticAccountResource($analyticAccount);
    }

    /**
     * Update an analytic account.
     */
    public function update(UpdateAnalyticAccountRequest $request, AnalyticAccount $analyticAccount): AnalyticAccountResource
    {
        $this->authorize('update', $analyticAccount);

        $analyticAccount->update($request->validated());

        return new AnalyticAccountResource($analyticAccount->fresh());
    }
}
