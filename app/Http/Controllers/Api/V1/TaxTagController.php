<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaxTagRequest;
use App\Http\Requests\Api\V1\UpdateTaxTagRequest;
use App\Http\Resources\Api\V1\TaxTagResource;
use App\Models\Accounting\TaxTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxTagController extends Controller
{
    /**
     * List tax tags / tax grids.
     *
     * @queryParam search string Search by code or name. Example: PPN
     * @queryParam applicability string Filter by base or tax. Example: tax
     * @queryParam is_active bool Filter by active status. Example: 1
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TaxTag::class);

        $query = TaxTag::query()->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($applicability = $request->string('applicability')->trim()->toString()) {
            $query->where('applicability', $applicability);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return TaxTagResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * Create a tax tag.
     */
    public function store(StoreTaxTagRequest $request): JsonResponse
    {
        $this->authorize('create', TaxTag::class);

        $tag = TaxTag::query()->create($request->validated());

        return (new TaxTagResource($tag))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a tax tag.
     */
    public function show(TaxTag $taxTag): TaxTagResource
    {
        $this->authorize('view', $taxTag);

        return new TaxTagResource($taxTag);
    }

    /**
     * Update a tax tag.
     */
    public function update(UpdateTaxTagRequest $request, TaxTag $taxTag): TaxTagResource
    {
        $this->authorize('update', $taxTag);

        $taxTag->update($request->validated());

        return new TaxTagResource($taxTag->fresh());
    }
}
