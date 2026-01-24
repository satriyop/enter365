<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\AccountFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccountRequest;
use App\Http\Requests\Api\V1\UpdateAccountRequest;
use App\Http\Resources\Api\V1\AccountResource;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(
        private AccountBalanceService $balanceService
    ) {}

    public function index(AccountFilter $filter): AnonymousResourceCollection
    {
        $accounts = Account::query()
            ->filter($filter)
            ->orderBy('code')
            ->paginate($filter->getRequest()->input('per_page', 50));

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $account = Account::create($request->validated());

        return new AccountResource($account->load('parent'));
    }

    public function show(Account $account, AccountFilter $filter): AccountResource
    {
        $filter->apply($account->newQuery());
        
        $account->loadMissing(['parent', 'children']);

        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        if ($account->is_system && $request->has('code')) {
            abort(422, 'Tidak bisa mengubah kode akun sistem.');
        }

        $account->update($request->validated());

        return new AccountResource($account->fresh(['parent', 'children']));
    }

    public function destroy(Account $account): JsonResponse
    {
        if ($account->is_system) {
            abort(422, 'Tidak bisa menghapus akun sistem.');
        }

        if ($account->journalEntryLines()->exists()) {
            abort(422, 'Tidak bisa menghapus akun yang sudah digunakan dalam jurnal.');
        }

        $account->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }

    /**
     * Get account balance.
     * 
     * @response array{account_id: int, code: string, name: string, type: string, as_of_date: string, balance: int}
     */
    public function balance(Account $account, Request $request): JsonResponse
    {
        $asOfDate = $request->input('as_of_date');
        $balance = $this->balanceService->getBalance($account, $asOfDate);

        return response()->json([
            'account_id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'as_of_date' => $asOfDate ?? now()->toDateString(),
            'balance' => $balance,
        ]);
    }

    /**
     * Get account ledger.
     * 
     * @response array{account_id: int, code: string, name: string, type: string, start_date: string|null, end_date: string|null, opening_balance: int, entries: array<mixed>}
     */
    public function ledger(Account $account, Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $ledger = $this->balanceService->getLedger($account, $startDate, $endDate);

        return response()->json([
            'account_id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'opening_balance' => $account->opening_balance,
            'entries' => $ledger,
        ]);
    }
}
