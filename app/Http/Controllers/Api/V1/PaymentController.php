<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Shared\PaymentServiceInterface;
use App\Filters\PaymentFilter;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Shared\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
        $this->authorize('viewAny', Payment::class);

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
        $this->authorize('create', Payment::class);

        $payment = $this->paymentService->create($request->validated());

        return $this->created(new PaymentResource($payment), 'Pembayaran berhasil dibuat.');
    }

    public function show(Payment $payment, PaymentFilter $filter): PaymentResource
    {
        $this->authorize('view', $payment);

        $filter->apply($payment->newQuery());

        $payment->loadMissing(['contact', 'cashAccount', 'journalEntry.lines.account']);

        return new PaymentResource($payment);
    }

    public function void(Payment $payment): JsonResponse
    {
        $this->authorize('void', $payment);

        $voidedPayment = $this->paymentService->void($payment);

        return $this->success(
            new PaymentResource($voidedPayment),
            'Pembayaran berhasil dibatalkan.'
        );
    }
}
