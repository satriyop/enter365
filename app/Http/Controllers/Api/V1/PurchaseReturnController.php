<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePurchaseReturnRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseReturnRequest;
use App\Http\Resources\Api\V1\PurchaseReturnResource;
use App\Models\Accounting\Bill;
use App\Models\Accounting\PurchaseReturn;
use App\Services\Accounting\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private PurchaseReturnService $purchaseReturnService
    ) {}

    /**
     * Display a listing of purchase returns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseReturn::query()
            ->with(['contact', 'bill', 'warehouse', 'creator'])
            ->withCount('items');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by contact
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        // Filter by bill
        if ($request->has('bill_id')) {
            $query->where('bill_id', $request->bill_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('return_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('return_date', '<=', $request->end_date);
        }

        // Filter by reason
        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('bill', function ($q) use ($search) {
                        $q->where('bill_number', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'return_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $purchaseReturns = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return PurchaseReturnResource::collection($purchaseReturns);
    }

    /**
     * Store a newly created purchase return.
     */
    public function store(StorePurchaseReturnRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $purchaseReturn = $this->purchaseReturnService->create($data);

        return response()->json([
            'message' => 'Retur pembelian berhasil dibuat.',
            'data' => new PurchaseReturnResource($purchaseReturn),
        ], 201);
    }

    /**
     * Create purchase return from bill.
     */
    public function createFromBill(Request $request, Bill $bill): JsonResponse
    {
        $data = $request->validate([
            'return_date' => ['sometimes', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'reason' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['created_by'] = $request->user()?->id;

        $purchaseReturn = $this->purchaseReturnService->createFromBill($bill, $data);

        return response()->json([
            'message' => 'Retur pembelian berhasil dibuat dari bill.',
            'data' => new PurchaseReturnResource($purchaseReturn),
        ], 201);
    }

    /**
     * Display the specified purchase return.
     */
    public function show(PurchaseReturn $purchaseReturn): PurchaseReturnResource
    {
        $purchaseReturn->load(['items.product', 'contact', 'bill', 'warehouse', 'creator', 'journalEntry']);

        return new PurchaseReturnResource($purchaseReturn);
    }

    /**
     * Update the specified purchase return.
     */
    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->update($purchaseReturn, $request->validated());

            return response()->json([
                'message' => 'Retur pembelian berhasil diperbarui.',
                'data' => new PurchaseReturnResource($purchaseReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified purchase return.
     */
    public function destroy(PurchaseReturn $purchaseReturn): JsonResponse
    {
        try {
            $this->purchaseReturnService->delete($purchaseReturn);

            return response()->json(['message' => 'Retur pembelian berhasil dihapus.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit a purchase return for approval.
     */
    public function submit(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->submit(
                $purchaseReturn,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur pembelian berhasil diajukan.',
                'data' => new PurchaseReturnResource($purchaseReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Approve a purchase return.
     */
    public function approve(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->approve(
                $purchaseReturn,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur pembelian berhasil disetujui.',
                'data' => new PurchaseReturnResource($purchaseReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject a purchase return.
     */
    public function reject(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $purchaseReturn = $this->purchaseReturnService->reject(
                $purchaseReturn,
                $data['reason'] ?? null,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur pembelian berhasil ditolak.',
                'data' => new PurchaseReturnResource($purchaseReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete a purchase return.
     */
    public function complete(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->complete(
                $purchaseReturn,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur pembelian berhasil diselesaikan.',
                'data' => new PurchaseReturnResource($purchaseReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a purchase return.
     */
    public function cancel(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        try {
            $reason = $request->input('reason');
            $purchaseReturn = $this->purchaseReturnService->cancel($purchaseReturn, $reason);

            return response()->json([
                'message' => 'Retur pembelian berhasil dibatalkan.',
                'data' => new PurchaseReturnResource($purchaseReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get purchase returns for a bill.
     */
    public function forBill(Bill $bill): AnonymousResourceCollection
    {
        $purchaseReturns = $this->purchaseReturnService->getForBill($bill);

        return PurchaseReturnResource::collection($purchaseReturns);
    }

    /**
     * Get purchase return statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $stats = $this->purchaseReturnService->getStatistics($startDate, $endDate);

        return response()->json($stats);
    }
}
