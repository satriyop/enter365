<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTaxRecordRequest;
use App\Http\Resources\Api\V1\TaxRecordResource;
use App\Models\Inventory\Product;
use App\Models\Tax\TaxRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxRecordController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $query = TaxRecord::query()->orderBy('code');

        if ($request->filled('applicability')) {
            $applicability = $request->string('applicability')->toString();
            $query->where(function ($inner) use ($applicability) {
                $inner->where('applicability', $applicability)
                    ->orWhere('applicability', TaxRecord::APPLICABILITY_BOTH);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return TaxRecordResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreTaxRecordRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $tax = TaxRecord::query()->create($request->validated());

        return (new TaxRecordResource($tax))
            ->response()
            ->setStatusCode(201);
    }
}
