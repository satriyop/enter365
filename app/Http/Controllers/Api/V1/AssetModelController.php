<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\FixedAssetServiceInterface;
use App\Http\Requests\Api\V1\StoreAssetModelRequest;
use App\Http\Requests\Api\V1\UpdateAssetModelRequest;
use App\Http\Resources\Api\V1\AssetModelResource;
use App\Models\Accounting\AssetModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetModelController extends Controller
{
    public function __construct(
        private FixedAssetServiceInterface $fixedAssets,
    ) {}

    /**
     * List depreciation models (Odoo Asset Models).
     *
     * @queryParam search string Search by code or name. Example: VEHICLE
     * @queryParam is_active bool Filter by active status. Example: 1
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AssetModel::class);

        $query = AssetModel::query()->with('assetAccount')->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return AssetModelResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreAssetModelRequest $request): JsonResponse
    {
        $this->authorize('create', AssetModel::class);

        $model = $this->fixedAssets->createModel($request->validated());

        return (new AssetModelResource($model->load('assetAccount')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AssetModel $assetModel): AssetModelResource
    {
        $this->authorize('view', $assetModel);

        return new AssetModelResource($assetModel->load('assetAccount'));
    }

    public function update(UpdateAssetModelRequest $request, AssetModel $assetModel): AssetModelResource
    {
        $this->authorize('update', $assetModel);

        $model = $this->fixedAssets->updateModel($assetModel, $request->validated());

        return new AssetModelResource($model->load('assetAccount'));
    }

    public function destroy(AssetModel $assetModel): JsonResponse
    {
        $this->authorize('delete', $assetModel);

        $this->fixedAssets->deleteModel($assetModel);

        return $this->deleted('Model aset berhasil dihapus.');
    }
}
