<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Services\Accounting\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function __construct(
        private JournalService $journalService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()->with(['contact', 'cashAccount']);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('is_voided')) {
            $query->where('is_voided', $request->boolean('is_voided'));
        }

        if ($request->has('start_date')) {
            $query->where('payment_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('payment_date', '<=', $request->input('end_date'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(payment_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(reference) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('contact', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $payments = $query->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Handle invoice/bill allocation
            $payableType = null;
            $payableId = null;

            if (isset($data['invoice_id'])) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                if (! in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])) {
                    abort(422, 'Faktur tidak dalam status yang bisa dibayar.');
                }
                if ($data['amount'] > $invoice->getOutstandingAmount()) {
                    abort(422, 'Jumlah pembayaran melebihi sisa tagihan.');
                }
                $payableType = Invoice::class;
                $payableId = $invoice->id;
                unset($data['invoice_id']);
            }

            if (isset($data['bill_id'])) {
                $bill = Bill::findOrFail($data['bill_id']);
                if (! in_array($bill->status, [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL, Bill::STATUS_OVERDUE])) {
                    abort(422, 'Tagihan tidak dalam status yang bisa dibayar.');
                }
                if ($data['amount'] > $bill->getOutstandingAmount()) {
                    abort(422, 'Jumlah pembayaran melebihi sisa tagihan.');
                }
                $payableType = Bill::class;
                $payableId = $bill->id;
                unset($data['bill_id']);
            }

            $payment = Payment::create([
                ...$data,
                'payment_number' => Payment::generatePaymentNumber($data['type']),
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'created_by' => auth()->id(),
            ]);

            // Post to journal
            $this->journalService->postPayment($payment);

            return $payment->load(['contact', 'cashAccount', 'journalEntry.lines.account']);
        });

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource(
            $payment->load(['contact', 'cashAccount', 'journalEntry.lines.account'])
        );
    }

    public function void(Payment $payment): JsonResponse
    {
        if ($payment->is_voided) {
            abort(422, 'Pembayaran sudah dibatalkan.');
        }

        try {
            $this->journalService->voidPayment($payment);

            return response()->json([
                'message' => 'Pembayaran berhasil dibatalkan.',
                'payment' => new PaymentResource($payment->fresh(['contact', 'cashAccount'])),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
