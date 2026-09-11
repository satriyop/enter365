<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AccountingConfigServiceInterface;
use App\Http\Requests\Api\V1\StoreFollowUpLevelRequest;
use App\Http\Requests\Api\V1\UpdateFollowUpLevelRequest;
use App\Http\Resources\Api\V1\FollowUpLevelResource;
use App\Models\Accounting\FollowUpLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FollowUpLevelController extends Controller
{
    public function __construct(
        private AccountingConfigServiceInterface $config,
    ) {}

    /**
     * List dunning follow-up levels (Odoo Configuration › Follow-up Levels).
     *
     * @queryParam search string Search by name. Example: First
     * @queryParam is_active bool Filter by active. Example: 1
     * @queryParam per_page int Default 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FollowUpLevel::class);

        $query = FollowUpLevel::query()->orderBy('sequence')->orderBy('delay_days');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return FollowUpLevelResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreFollowUpLevelRequest $request): JsonResponse
    {
        $this->authorize('create', FollowUpLevel::class);

        $level = $this->config->createFollowUpLevel($request->validated());

        return (new FollowUpLevelResource($level))->response()->setStatusCode(201);
    }

    public function show(FollowUpLevel $followUpLevel): FollowUpLevelResource
    {
        $this->authorize('view', $followUpLevel);

        return new FollowUpLevelResource($followUpLevel);
    }

    public function update(UpdateFollowUpLevelRequest $request, FollowUpLevel $followUpLevel): FollowUpLevelResource
    {
        $this->authorize('update', $followUpLevel);

        return new FollowUpLevelResource($this->config->updateFollowUpLevel($followUpLevel, $request->validated()));
    }

    public function destroy(FollowUpLevel $followUpLevel): JsonResponse
    {
        $this->authorize('delete', $followUpLevel);

        $this->config->deleteFollowUpLevel($followUpLevel);

        return $this->deleted('Tingkat follow-up berhasil dihapus.');
    }
}
