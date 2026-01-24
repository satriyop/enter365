<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Shared\PaymentServiceInterface;
use App\Filters\PaymentFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Shared\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentServiceInterface $paymentService
    ) {}

    /**
     * Display a listing of payments.
     */
    public function index(PaymentFilter $filter): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(['contact', 'cashAccount']) // Default eager loads
            ->filter($filter)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->create($request->validated());

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Payment $payment, PaymentFilter $filter): PaymentResource
    {
        $filter->apply($payment->newQuery());

        $payment->loadMissing(['contact', 'cashAccount', 'journalEntry.lines.account']);

        return new PaymentResource($payment);
    }

    public function void(Payment $payment): JsonResponse
    {
        try {
            $voidedPayment = $this->paymentService->void($payment);

            return response()->json([
                'message' => 'Pembayaran berhasil dibatalkan.',
                'payment' => new PaymentResource($voidedPayment),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
