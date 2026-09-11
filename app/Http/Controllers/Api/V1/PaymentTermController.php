<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SavePaymentTermRequest;
use App\Http\Resources\Api\V1\PaymentTermResource;
use App\Models\Accounting\PaymentTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentTermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentTerm::class);

        $query = PaymentTerm::query()->orderBy('name')->orderBy('id');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return PaymentTermResource::collection($query->paginate(max(1, min(200, $request->integer('per_page', 50)))));
    }

    public function store(SavePaymentTermRequest $request): JsonResponse
    {
        $payment_term = PaymentTerm::query()->create($request->validated());

        return (new PaymentTermResource($payment_term->refresh()))->response()->setStatusCode(201);
    }

    public function show(PaymentTerm $payment_term): PaymentTermResource
    {
        $this->authorize('view', $payment_term);

        return new PaymentTermResource($payment_term);
    }

    public function update(SavePaymentTermRequest $request, PaymentTerm $payment_term): PaymentTermResource
    {
        $payment_term->update($request->validated());

        return new PaymentTermResource($payment_term->refresh());
    }

    public function preview(\App\Http\Requests\Api\V1\PreviewPaymentTermRequest $request, PaymentTerm $payment_term, \App\Services\Accounting\PaymentTermSchedule $schedule): JsonResponse
    {
        $this->authorize('view', $payment_term);

        return response()->json(['data' => $schedule->calculate($payment_term, $request->integer('amount'), $request->validated('date'))]);
    }
}
