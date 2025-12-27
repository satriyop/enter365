<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSalesReturnRequest;
use App\Http\Requests\Api\V1\UpdateSalesReturnRequest;
use App\Http\Resources\Api\V1\SalesReturnResource;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\SalesReturn;
use App\Services\Accounting\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesReturnController extends Controller
{
    public function __construct(
        private SalesReturnService $salesReturnService
    ) {}

    /**
     * Display a listing of sales returns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesReturn::query()
            ->with(['contact', 'invoice', 'warehouse', 'creator'])
            ->withCount('items');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by contact
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        // Filter by invoice
        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
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
                    ->orWhereHas('invoice', function ($q) use ($search) {
                        $q->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'return_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $salesReturns = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return SalesReturnResource::collection($salesReturns);
    }

    /**
     * Store a newly created sales return.
     */
    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $salesReturn = $this->salesReturnService->create($data);

        return response()->json([
            'message' => 'Retur penjualan berhasil dibuat.',
            'data' => new SalesReturnResource($salesReturn),
        ], 201);
    }

    /**
     * Create sales return from invoice.
     */
    public function createFromInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'return_date' => ['sometimes', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'reason' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['created_by'] = $request->user()?->id;

        $salesReturn = $this->salesReturnService->createFromInvoice($invoice, $data);

        return response()->json([
            'message' => 'Retur penjualan berhasil dibuat dari invoice.',
            'data' => new SalesReturnResource($salesReturn),
        ], 201);
    }

    /**
     * Display the specified sales return.
     */
    public function show(SalesReturn $salesReturn): SalesReturnResource
    {
        $salesReturn->load(['items.product', 'contact', 'invoice', 'warehouse', 'creator', 'journalEntry']);

        return new SalesReturnResource($salesReturn);
    }

    /**
     * Update the specified sales return.
     */
    public function update(UpdateSalesReturnRequest $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $salesReturn = $this->salesReturnService->update($salesReturn, $request->validated());

            return response()->json([
                'message' => 'Retur penjualan berhasil diperbarui.',
                'data' => new SalesReturnResource($salesReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified sales return.
     */
    public function destroy(SalesReturn $salesReturn): JsonResponse
    {
        try {
            $this->salesReturnService->delete($salesReturn);

            return response()->json(['message' => 'Retur penjualan berhasil dihapus.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit a sales return for approval.
     */
    public function submit(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $salesReturn = $this->salesReturnService->submit(
                $salesReturn,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur penjualan berhasil diajukan.',
                'data' => new SalesReturnResource($salesReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Approve a sales return.
     */
    public function approve(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $salesReturn = $this->salesReturnService->approve(
                $salesReturn,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur penjualan berhasil disetujui.',
                'data' => new SalesReturnResource($salesReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject a sales return.
     */
    public function reject(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $salesReturn = $this->salesReturnService->reject(
                $salesReturn,
                $data['reason'] ?? null,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur penjualan berhasil ditolak.',
                'data' => new SalesReturnResource($salesReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete a sales return.
     */
    public function complete(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $salesReturn = $this->salesReturnService->complete(
                $salesReturn,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Retur penjualan berhasil diselesaikan.',
                'data' => new SalesReturnResource($salesReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a sales return.
     */
    public function cancel(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        try {
            $reason = $request->input('reason');
            $salesReturn = $this->salesReturnService->cancel($salesReturn, $reason);

            return response()->json([
                'message' => 'Retur penjualan berhasil dibatalkan.',
                'data' => new SalesReturnResource($salesReturn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get sales returns for an invoice.
     */
    public function forInvoice(Invoice $invoice): AnonymousResourceCollection
    {
        $salesReturns = $this->salesReturnService->getForInvoice($invoice);

        return SalesReturnResource::collection($salesReturns);
    }

    /**
     * Get sales return statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $stats = $this->salesReturnService->getStatistics($startDate, $endDate);

        return response()->json($stats);
    }
}
