<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AccountingTransferServiceInterface;
use App\Http\Requests\Api\V1\StoreAccountingTransferRequest;
use App\Http\Requests\Api\V1\UpdateAccountingTransferRequest;
use App\Http\Resources\Api\V1\AccountingTransferResource;
use App\Models\Accounting\AccountingTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountingTransferController extends Controller
{
    public function __construct(
        private AccountingTransferServiceInterface $transfers,
    ) {}

    /**
     * List inter-journal/account transfers (Odoo Accounting › Transfers).
     *
     * @queryParam search string Search by number or memo. Example: TRF
     * @queryParam status string Filter by draft, posted, or cancelled. Example: draft
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccountingTransfer::class);

        $query = AccountingTransfer::query()
            ->with(['fromJournal', 'toJournal', 'fromAccount', 'toAccount'])
            ->orderByDesc('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('transfer_number', 'like', '%'.$search.'%')
                    ->orWhere('memo', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return AccountingTransferResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreAccountingTransferRequest $request): JsonResponse
    {
        $this->authorize('create', AccountingTransfer::class);

        $transfer = $this->transfers->create($request->validated());

        return (new AccountingTransferResource($transfer->load([
            'fromJournal',
            'toJournal',
            'fromAccount',
            'toAccount',
        ])))->response()->setStatusCode(201);
    }

    public function show(AccountingTransfer $accountingTransfer): AccountingTransferResource
    {
        $this->authorize('view', $accountingTransfer);

        return new AccountingTransferResource($accountingTransfer->load([
            'fromJournal',
            'toJournal',
            'fromAccount',
            'toAccount',
            'journalEntry',
        ]));
    }

    public function update(UpdateAccountingTransferRequest $request, AccountingTransfer $accountingTransfer): AccountingTransferResource
    {
        $this->authorize('update', $accountingTransfer);

        return new AccountingTransferResource(
            $this->transfers->update($accountingTransfer, $request->validated())
        );
    }

    public function destroy(AccountingTransfer $accountingTransfer): JsonResponse
    {
        $this->authorize('delete', $accountingTransfer);

        $this->transfers->delete($accountingTransfer);

        return $this->deleted('Transfer akuntansi berhasil dihapus.');
    }

    /**
     * Post a draft transfer: debit destination, credit source.
     */
    public function post(AccountingTransfer $accountingTransfer): AccountingTransferResource
    {
        $this->authorize('update', $accountingTransfer);

        return new AccountingTransferResource($this->transfers->post($accountingTransfer));
    }

    /**
     * Reverse a posted transfer.
     */
    public function cancel(Request $request, AccountingTransfer $accountingTransfer): AccountingTransferResource
    {
        $this->authorize('update', $accountingTransfer);

        return new AccountingTransferResource(
            $this->transfers->cancel($accountingTransfer, $request->string('reason')->trim()->toString() ?: null)
        );
    }
}
