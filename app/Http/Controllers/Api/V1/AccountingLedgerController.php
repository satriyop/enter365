<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AccountingConfigServiceInterface;
use App\Http\Requests\Api\V1\StoreAccountingLedgerRequest;
use App\Http\Requests\Api\V1\UpdateAccountingLedgerRequest;
use App\Http\Resources\Api\V1\AccountingLedgerResource;
use App\Models\Accounting\AccountingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountingLedgerController extends Controller
{
    public function __construct(
        private AccountingConfigServiceInterface $config,
    ) {}

    /**
     * List accounting ledgers (Odoo Configuration › Multi Ledgers).
     *
     * @queryParam search string Search by code or name. Example: IFRS
     * @queryParam is_active bool Filter by active. Example: 1
     * @queryParam per_page int Default 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccountingLedger::class);

        $query = AccountingLedger::query()->with('currency')->orderByDesc('is_default')->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return AccountingLedgerResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreAccountingLedgerRequest $request): JsonResponse
    {
        $this->authorize('create', AccountingLedger::class);

        $ledger = $this->config->createLedger($request->validated());

        return (new AccountingLedgerResource($ledger->load('currency')))->response()->setStatusCode(201);
    }

    public function show(AccountingLedger $accountingLedger): AccountingLedgerResource
    {
        $this->authorize('view', $accountingLedger);

        return new AccountingLedgerResource($accountingLedger->load('currency'));
    }

    public function update(UpdateAccountingLedgerRequest $request, AccountingLedger $accountingLedger): AccountingLedgerResource
    {
        $this->authorize('update', $accountingLedger);

        return new AccountingLedgerResource($this->config->updateLedger($accountingLedger, $request->validated()));
    }

    public function destroy(AccountingLedger $accountingLedger): JsonResponse
    {
        $this->authorize('delete', $accountingLedger);

        $this->config->deleteLedger($accountingLedger);

        return $this->deleted('Buku besar berhasil dihapus.');
    }
}
