<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SavePaymentProviderRequest;
use App\Http\Resources\Api\V1\PaymentProviderResource;
use App\Models\Accounting\PaymentProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentProviderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentProvider::class);

        $query = PaymentProvider::query()->with('paymentMethods')->orderBy('name')->orderBy('id');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return PaymentProviderResource::collection($query->paginate(max(1, min(200, $request->integer('per_page', 50)))));
    }

    public function store(SavePaymentProviderRequest $request): JsonResponse
    {
        $payment_provider = (new PaymentProvider)->getConnection()->transaction(function () use ($request): PaymentProvider {
            $record = PaymentProvider::query()->create($request->safe()->except('payment_method_ids'));
            $record->paymentMethods()->sync($request->validated('payment_method_ids', []));

            return $record;
        });

        return (new PaymentProviderResource($payment_provider->refresh()->load('paymentMethods')))->response()->setStatusCode(201);
    }

    public function show(PaymentProvider $payment_provider): PaymentProviderResource
    {
        $this->authorize('view', $payment_provider);

        return new PaymentProviderResource($payment_provider->load('paymentMethods'));
    }

    public function update(SavePaymentProviderRequest $request, PaymentProvider $payment_provider): PaymentProviderResource
    {
        $payment_provider->getConnection()->transaction(function () use ($request, $payment_provider): void {
            $payment_provider->update($request->safe()->except('payment_method_ids'));
            if ($request->has('payment_method_ids')) {
                $payment_provider->paymentMethods()->sync($request->validated('payment_method_ids'));
            }
        });

        return new PaymentProviderResource($payment_provider->refresh()->load('paymentMethods'));
    }
}
