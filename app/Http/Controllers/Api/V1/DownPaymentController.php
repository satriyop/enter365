<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyDownPaymentRequest;
use App\Http\Requests\Api\V1\RefundDownPaymentRequest;
use App\Http\Requests\Api\V1\StoreDownPaymentRequest;
use App\Http\Requests\Api\V1\UpdateDownPaymentRequest;
use App\Http\Resources\Api\V1\DownPaymentApplicationResource;
use App\Http\Resources\Api\V1\DownPaymentResource;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DownPayment;
use App\Models\Accounting\DownPaymentApplication;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\DownPaymentService;
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
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DownPayment::query()
            ->with(['contact', 'cashAccount', 'creator'])
            ->withCount('applications');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by contact
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('dp_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('dp_date', '<=', $request->end_date);
        }

        // Filter available only (has remaining balance)
        if ($request->boolean('available_only')) {
            $query->where('status', DownPayment::STATUS_ACTIVE)
                ->whereRaw('applied_amount < amount');
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('dp_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'dp_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $downPayments = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

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
    public function show(DownPayment $downPayment): DownPaymentResource
    {
        $downPayment->load(['contact', 'cashAccount', 'creator', 'applications.applicable', 'journalEntry']);

        return new DownPaymentResource($downPayment);
    }

    /**
     * Update the specified down payment.
     */
    public function update(UpdateDownPaymentRequest $request, DownPayment $downPayment): JsonResponse
    {
        try {
            $downPayment = $this->downPaymentService->update($downPayment, $request->validated());

            return response()->json([
                'message' => 'Down payment updated successfully.',
                'data' => new DownPaymentResource($downPayment),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified down payment.
     */
    public function destroy(DownPayment $downPayment): JsonResponse
    {
        try {
            $this->downPaymentService->delete($downPayment);

            return response()->json(['message' => 'Down payment deleted successfully.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Apply down payment to an invoice.
     */
    public function applyToInvoice(ApplyDownPaymentRequest $request, DownPayment $downPayment, Invoice $invoice): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['created_by'] = $request->user()?->id;

            $application = $this->downPaymentService->applyToInvoice($downPayment, $invoice, $data);

            return response()->json([
                'message' => 'Down payment applied to invoice successfully.',
                'application' => new DownPaymentApplicationResource($application),
                'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount'])),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Apply down payment to a bill.
     */
    public function applyToBill(ApplyDownPaymentRequest $request, DownPayment $downPayment, Bill $bill): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['created_by'] = $request->user()?->id;

            $application = $this->downPaymentService->applyToBill($downPayment, $bill, $data);

            return response()->json([
                'message' => 'Down payment applied to bill successfully.',
                'application' => new DownPaymentApplicationResource($application),
                'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount'])),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unapply (reverse) a down payment application.
     */
    public function unapply(DownPayment $downPayment, DownPaymentApplication $application): JsonResponse
    {
        if ($application->down_payment_id !== $downPayment->id) {
            return response()->json(['message' => 'Application does not belong to this down payment.'], 422);
        }

        try {
            $this->downPaymentService->unapply($application);

            return response()->json([
                'message' => 'Down payment application reversed successfully.',
                'down_payment' => new DownPaymentResource($downPayment->fresh(['contact', 'cashAccount', 'applications'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Refund remaining down payment balance.
     */
    public function refund(RefundDownPaymentRequest $request, DownPayment $downPayment): JsonResponse
    {
        try {
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
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a down payment.
     */
    public function cancel(Request $request, DownPayment $downPayment): JsonResponse
    {
        try {
            $reason = $request->input('reason');
            $downPayment = $this->downPaymentService->cancel($downPayment, $reason);

            return response()->json([
                'message' => 'Down payment cancelled successfully.',
                'data' => new DownPaymentResource($downPayment),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get available down payments for a contact.
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
                'active' => (clone $query)->where('status', DownPayment::STATUS_ACTIVE)->count(),
                'fully_applied' => (clone $query)->where('status', DownPayment::STATUS_FULLY_APPLIED)->count(),
                'refunded' => (clone $query)->where('status', DownPayment::STATUS_REFUNDED)->count(),
                'cancelled' => (clone $query)->where('status', DownPayment::STATUS_CANCELLED)->count(),
            ],
            'by_type' => [
                'receivable' => [
                    'count' => DownPayment::where('type', DownPayment::TYPE_RECEIVABLE)->count(),
                    'amount' => DownPayment::where('type', DownPayment::TYPE_RECEIVABLE)->sum('amount'),
                    'remaining' => DownPayment::where('type', DownPayment::TYPE_RECEIVABLE)
                        ->where('status', DownPayment::STATUS_ACTIVE)
                        ->selectRaw('SUM(amount - applied_amount) as remaining')->value('remaining') ?? 0,
                ],
                'payable' => [
                    'count' => DownPayment::where('type', DownPayment::TYPE_PAYABLE)->count(),
                    'amount' => DownPayment::where('type', DownPayment::TYPE_PAYABLE)->sum('amount'),
                    'remaining' => DownPayment::where('type', DownPayment::TYPE_PAYABLE)
                        ->where('status', DownPayment::STATUS_ACTIVE)
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
