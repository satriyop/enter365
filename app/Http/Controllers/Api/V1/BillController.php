<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\Domains\BillServiceInterface;
use App\Filters\BillFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MakeRecurringRequest;
use App\Http\Requests\Api\V1\StoreBillRequest;
use App\Http\Requests\Api\V1\UpdateBillRequest;
use App\Http\Resources\Api\V1\BillResource;
use App\Http\Resources\Api\V1\RecurringTemplateResource;
use App\Models\Purchasing\Bill;
use App\Services\Sales\RecurringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class BillController extends Controller
{
    public function __construct(
        private BillServiceInterface $billService,
        private RecurringService $recurringService
    ) {}

    /**
     * Display a listing of bills.
     */
    public function index(BillFilter $filter): AnonymousResourceCollection
    {
        $bills = Bill::query()
            ->with(['contact', 'items'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return BillResource::collection($bills);
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $bill = $this->billService->create($request->validated());

        return (new BillResource($bill))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Bill $bill): BillResource
    {
        return new BillResource(
            $bill->load(['contact', 'items.expenseAccount', 'journalEntry.lines.account', 'payments'])
        );
    }

    public function update(UpdateBillRequest $request, Bill $bill): BillResource|JsonResponse
    {
        try {
            $bill = $this->billService->update($bill, $request->validated());

            return new BillResource($bill);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Bill $bill): JsonResponse
    {
        try {
            $this->billService->delete($bill);

            return response()->json(['message' => 'Tagihan berhasil dihapus.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function post(Bill $bill): BillResource|JsonResponse
    {
        try {
            $bill = $this->billService->post($bill);

            return new BillResource($bill);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function makeRecurring(MakeRecurringRequest $request, Bill $bill): JsonResponse
    {
        $bill->load('items');

        $template = $this->recurringService->createTemplateFromBill($bill, $request->validated());

        return response()->json([
            'message' => 'Template recurring berhasil dibuat dari tagihan.',
            'data' => new RecurringTemplateResource($template->load('contact')),
        ], 201);
    }
}
