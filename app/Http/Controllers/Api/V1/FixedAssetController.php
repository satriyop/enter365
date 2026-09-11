<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\FixedAssetServiceInterface;
use App\Http\Requests\Api\V1\StoreFixedAssetRequest;
use App\Http\Requests\Api\V1\UpdateFixedAssetRequest;
use App\Http\Resources\Api\V1\FixedAssetResource;
use App\Models\Accounting\FixedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FixedAssetController extends Controller
{
    public function __construct(
        private FixedAssetServiceInterface $fixedAssets,
    ) {}

    /**
     * List the fixed asset register (Odoo Accounting › Assets).
     *
     * @queryParam search string Search by code or name. Example: AVANZA
     * @queryParam status string Filter by draft, running, or closed. Example: running
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FixedAsset::class);

        $query = FixedAsset::query()->with('assetModel')->orderByDesc('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return FixedAssetResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreFixedAssetRequest $request): JsonResponse
    {
        $this->authorize('create', FixedAsset::class);

        $asset = $this->fixedAssets->createAsset($request->validated());

        return (new FixedAssetResource($asset->load(['assetModel', 'depreciationLines'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(FixedAsset $fixedAsset): FixedAssetResource
    {
        $this->authorize('view', $fixedAsset);

        return new FixedAssetResource($fixedAsset->load(['assetModel', 'depreciationLines']));
    }

    public function update(UpdateFixedAssetRequest $request, FixedAsset $fixedAsset): FixedAssetResource
    {
        $this->authorize('update', $fixedAsset);

        $asset = $this->fixedAssets->updateAsset($fixedAsset, $request->validated());

        return new FixedAssetResource($asset);
    }

    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        $this->authorize('delete', $fixedAsset);

        $this->fixedAssets->deleteAsset($fixedAsset);

        return $this->deleted('Aset tetap berhasil dihapus.');
    }

    /**
     * Confirm a draft asset and generate its depreciation schedule.
     */
    public function confirm(FixedAsset $fixedAsset): FixedAssetResource
    {
        $this->authorize('update', $fixedAsset);

        return new FixedAssetResource($this->fixedAssets->confirm($fixedAsset));
    }

    /**
     * Post the next draft depreciation line to the journal.
     */
    public function postDepreciation(FixedAsset $fixedAsset): FixedAssetResource
    {
        $this->authorize('update', $fixedAsset);

        return new FixedAssetResource($this->fixedAssets->postNextDepreciation($fixedAsset));
    }
}
