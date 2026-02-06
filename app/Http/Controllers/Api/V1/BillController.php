<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Filters\BillFilter;
use App\Http\Requests\Api\V1\MakeRecurringRequest;
use App\Http\Requests\Api\V1\StoreBillRequest;
use App\Http\Requests\Api\V1\UpdateBillRequest;
use App\Http\Requests\Api\V1\VoidBillRequest;
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
        $this->authorize('viewAny', Bill::class);

        $bills = Bill::query()
            ->with(['contact', 'items'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return BillResource::collection($bills);
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $this->authorize('create', Bill::class);

        $bill = $this->billService->create($request->validated());

        return (new BillResource($bill))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Bill $bill): BillResource
    {
        $this->authorize('view', $bill);

        return new BillResource(
            $bill->load(['contact', 'items.expenseAccount', 'journalEntry.lines.account', 'payments'])
        );
    }

    public function update(UpdateBillRequest $request, Bill $bill): BillResource|JsonResponse
    {
        $this->authorize('update', $bill);

        try {
            $bill = $this->billService->update($bill, $request->validated());

            return new BillResource($bill);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Bill $bill): JsonResponse
    {
        $this->authorize('delete', $bill);

        try {
            $this->billService->delete($bill);

            return $this->deleted('Tagihan berhasil dihapus.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function post(Bill $bill): BillResource|JsonResponse
    {
        $this->authorize('post', $bill);

        try {
            $bill = $this->billService->post($bill);

            return new BillResource($bill);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Void/cancel a posted bill.
     *
     * Cancels the bill and reverses any associated journal entries.
     */
    public function void(VoidBillRequest $request, Bill $bill): JsonResponse
    {
        $this->authorize('void', $bill);

        try {
            $bill = $this->billService->void($bill, $request->validated('reason'));

            return $this->success(new BillResource($bill), 'Tagihan berhasil dibatalkan.');
        } catch (\App\Exceptions\Domain\StateTransitionException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function makeRecurring(MakeRecurringRequest $request, Bill $bill): JsonResponse
    {
        $this->authorize('update', $bill);

        $bill->load('items');

        $template = $this->recurringService->createTemplateFromBill($bill, $request->validated());

        return $this->created(
            new RecurringTemplateResource($template->load('contact')),
            'Template recurring berhasil dibuat dari tagihan.'
        );
    }
}
