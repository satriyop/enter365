<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SavePaymentMethodRequest;
use App\Http\Resources\Api\V1\PaymentMethodResource;
use App\Models\Accounting\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $query = PaymentMethod::query()->orderBy('name')->orderBy('id');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return PaymentMethodResource::collection($query->paginate(max(1, min(200, $request->integer('per_page', 50)))));
    }

    public function store(SavePaymentMethodRequest $request): JsonResponse
    {
        $payment_method = PaymentMethod::query()->create($request->validated());

        return (new PaymentMethodResource($payment_method->refresh()))->response()->setStatusCode(201);
    }

    public function show(PaymentMethod $payment_method): PaymentMethodResource
    {
        $this->authorize('view', $payment_method);

        return new PaymentMethodResource($payment_method);
    }

    public function update(SavePaymentMethodRequest $request, PaymentMethod $payment_method): PaymentMethodResource
    {
        $payment_method->update($request->validated());

        return new PaymentMethodResource($payment_method->refresh());
    }
}
