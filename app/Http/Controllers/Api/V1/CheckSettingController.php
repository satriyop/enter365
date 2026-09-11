<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveCheckSettingRequest;
use App\Http\Resources\Api\V1\CheckSettingResource;
use App\Models\Accounting\CheckSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckSettingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CheckSetting::class);

        $query = CheckSetting::query()->orderBy('name')->orderBy('id');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CheckSettingResource::collection($query->paginate(max(1, min(200, $request->integer('per_page', 50)))));
    }

    public function store(SaveCheckSettingRequest $request): JsonResponse
    {
        $check = CheckSetting::query()->create($request->validated());

        return (new CheckSettingResource($check->refresh()))->response()->setStatusCode(201);
    }

    public function show(CheckSetting $check): CheckSettingResource
    {
        $this->authorize('view', $check);

        return new CheckSettingResource($check);
    }

    public function update(SaveCheckSettingRequest $request, CheckSetting $check): CheckSettingResource
    {
        $check->update($request->validated());

        return new CheckSettingResource($check->refresh());
    }
}
