<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Filters\DownPaymentFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyDownPaymentRequest;
use App\Http\Requests\Api\V1\RefundDownPaymentRequest;
use App\Http\Requests\Api\V1\StoreDownPaymentRequest;
use App\Http\Requests\Api\V1\UpdateDownPaymentRequest;
use App\Http\Resources\Api\V1\DownPaymentApplicationResource;
use App\Http\Resources\Api\V1\DownPaymentResource;
use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Services\Sales\DownPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DownPaymentController extends Controller
{
    public function __construct(
        private DownPaymentService $downPaymentService
    ) {}

    /**
     * Display a listing of down payments.
     */
    public function index(DownPaymentFilter $filter): AnonymousResourceCollection
    {
        $downPayments = DownPayment::query()
            ->with(['contact', 'cashAccount']) // Default eager loads
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return DownPaymentResource::collection($downPayments);
    }

    /**
     * Store a newly created down payment.
     */
    public function store(StoreDownPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $downPayment = $this->downPaymentService->create($data);

        return response()->json([
            'message' => 'Down payment created successfully.',
            'data' => new DownPaymentResource($downPayment),
        ], 201);
    }

    /**
     * Display the specified down payment.
     */
    public function show(DownPayment $downPayment, DownPaymentFilter $filter): DownPaymentResource
    {
        $filter->apply($downPayment->newQuery());

        $downPayment->loadMissing(['contact', 'cashAccount', 'creator', 'applications.applicable', 'journalEntry']);

        return new DownPaymentResource($downPayment);
    }

    /**
     * Update the specified down payment.
     */
    public function update(UpdateDownPaymentRequest $request, DownPayment $downPayment): JsonResponse
    {
        $downPayment = $this->downPaymentService->update($downPayment, $request->validated());

        return response()->json([
            'message' => 'Down payment updated successfully.',
            'data' => new DownPaymentResource($downPayment),
        ]);
    }

    /**
     * Remove the specified down payment.
     */
    public function destroy(DownPayment $downPayment): JsonResponse
    {
        $this->downPaymentService->delete($downPayment);

        return response()->json(['message' => 'Down payment deleted successfully.']);
    }

    /**
     * Apply down payment to an invoice.
     * 
     * @response array{message: string, application: DownPaymentApplicationResource, down_payment: DownPaymentResource}
     */
    public function applyToInvoice(ApplyDownPaymentRequest $request, DownPayment $downPayment, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $application = $this->downPaymentService->applyToInvoice($downPayment, $invoice, $data);

        return response()->json([
            'message' => 'Down payment applied to invoice successfully.',
            'application' => new DownPaymentApplicationResource($application),
            'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount'])),
        ], 201);
    }

    /**
     * Apply down payment to a bill.
     * 
     * @response array{message: string, application: DownPaymentApplicationResource, down_payment: DownPaymentResource}
     */
    public function applyToBill(ApplyDownPaymentRequest $request, DownPayment $downPayment, Bill $bill): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $application = $this->downPaymentService->applyToBill($downPayment, $bill, $data);

        return response()->json([
            'message' => 'Down payment applied to bill successfully.',
            'application' => new DownPaymentApplicationResource($application),
            'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount'])),
        ], 201);
    }

    /**
     * Unapply (reverse) a down payment application.
     * 
     * @response array{message: string, down_payment: DownPaymentResource}
     */
    public function unapply(DownPayment $downPayment, DownPaymentApplication $application): JsonResponse
    {
        if ($application->down_payment_id !== $downPayment->id) {
            return response()->json(['message' => 'Application does not belong to this down payment.'], 422);
        }

        $this->downPaymentService->unapply($application);

        return response()->json([
            'message' => 'Down payment application reversed successfully.',
            'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount', 'applications'])),
        ]);
    }

    /**
     * Refund remaining down payment balance.
     * 
     * @response array{message: string, refund_payment: array{id: int, payment_number: string, amount: int}, down_payment: DownPaymentResource}
     */
    public function refund(RefundDownPaymentRequest $request, DownPayment $downPayment): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $payment = $this->downPaymentService->refund($downPayment, $data);

        return response()->json([
            'message' => 'Down payment refunded successfully.',
            'refund_payment' => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
            ],
            'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount'])),
        ]);
    }

    /**
     * Cancel a down payment.
     */
    public function cancel(Request $request, DownPayment $downPayment): JsonResponse
    {
        $reason = $request->input('reason');
        $downPayment = $this->downPaymentService->cancel($downPayment, $reason);

        return response()->json([
            'message' => 'Down payment cancelled successfully.',
            'data' => new DownPaymentResource($downPayment),
        ]);
    }

    /**
     * Get available down payments for a contact.
     * 
     * @response \Illuminate\Http\Resources\Json\AnonymousResourceCollection<DownPaymentResource>
     */
    public function available(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'type' => ['required', 'in:receivable,payable'],
        ]);

        $downPayments = $this->downPaymentService->getAvailableForContact(
            $request->contact_id,
            $request->type
        );

        return DownPaymentResource::collection($downPayments);
    }

    /**
     * Get down payment statistics.
     * 
     * @response array{total_count: int, total_amount: int, total_applied: int, total_remaining: int, by_status: array{active: int, fully_applied: int, refunded: int, cancelled: int}, by_type: array{receivable: array{count: int, amount: int, remaining: int}, payable: array{count: int, amount: int, remaining: int}}}
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = DownPayment::query();

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('dp_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('dp_date', '<=', $request->end_date);
        }

        $stats = [
            'total_count' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('amount'),
            'total_applied' => (clone $query)->sum('applied_amount'),
            'total_remaining' => (clone $query)->selectRaw('SUM(amount - applied_amount) as remaining')->value('remaining') ?? 0,
            'by_status' => [
                'active' => (clone $query)->where('status', DocumentStatus::Active)->count(),
                'fully_applied' => (clone $query)->where('status', DocumentStatus::FullyApplied)->count(),
                'refunded' => (clone $query)->where('status', DocumentStatus::Refunded)->count(),
                'cancelled' => (clone $query)->where('status', DocumentStatus::Cancelled)->count(),
            ],
            'by_type' => [
                'receivable' => [
                    'count' => DownPayment::where('type', DownPayment::TYPE_RECEIVABLE)->count(),
                    'amount' => DownPayment::where('type', DownPayment::TYPE_RECEIVABLE)->sum('amount'),
                    'remaining' => DownPayment::where('type', DownPayment::TYPE_RECEIVABLE)
                        ->where('status', DocumentStatus::Active)
                        ->selectRaw('SUM(amount - applied_amount) as remaining')->value('remaining') ?? 0,
                ],
                'payable' => [
                    'count' => DownPayment::where('type', DownPayment::TYPE_PAYABLE)->count(),
                    'amount' => DownPayment::where('type', DownPayment::TYPE_PAYABLE)->sum('amount'),
                    'remaining' => DownPayment::where('type', DownPayment::TYPE_PAYABLE)
                        ->where('status', DocumentStatus::Active)
                        ->selectRaw('SUM(amount - applied_amount) as remaining')->value('remaining') ?? 0,
                ],
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Get applications for a down payment.
     */
    public function applications(DownPayment $downPayment): AnonymousResourceCollection
    {
        $applications = $downPayment->applications()
            ->with(['applicable', 'creator'])
            ->orderBy('applied_date', 'desc')
            ->get();

        return DownPaymentApplicationResource::collection($applications);
    }
}
