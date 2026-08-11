<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\AccountFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccountRequest;
use App\Http\Requests\Api\V1\UpdateAccountRequest;
use App\Http\Resources\Api\V1\AccountResource;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(
        private AccountBalanceService $balanceService,
        private AccountService $accountService
    ) {}

    public function index(AccountFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->filter($filter)
            ->orderBy('code')
            ->paginate($filter->getRequest()->input('per_page', 50));

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $this->authorize('create', Account::class);

        $account = $this->accountService->create($request->validated());

        return new AccountResource($account->load('parent'));
    }

    public function show(Account $account, AccountFilter $filter): AccountResource
    {
        $this->authorize('view', $account);

        $filter->apply($account->newQuery());

        $account->loadMissing(['parent', 'children']);

        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource|JsonResponse
    {
        $this->authorize('update', $account);

        try {
            $updatedAccount = $this->accountService->update($account, $request->validated());

            return new AccountResource($updatedAccount);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->accountService->delete($account);

            return response()->json(['message' => 'Akun berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get account balance.
     *
     * @response array{account_id: int, code: string, name: string, type: string, as_of_date: string, balance: int, total_debit: int, total_credit: int}
     */
    public function balance(Account $account, Request $request): JsonResponse
    {
        $this->authorize('view', $account);

        $asOfDate = $request->input('as_of_date');
        $details = $account->getBalanceDetails($asOfDate);

        return response()->json([
            'account_id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'as_of_date' => $asOfDate ?? now()->toDateString(),
            'balance' => $details['balance'],
            'total_debit' => $details['total_debit'],
            'total_credit' => $details['total_credit'],
        ]);
    }

    /**
     * Get account ledger.
     *
     * @response array{account_id: int, code: string, name: string, type: string, start_date: string|null, end_date: string|null, opening_balance: int, entries: array<array{id: int, journal_entry_id: int, date: string, entry_number: string, description: string, reference: string|null, debit: int, credit: int, balance: int}>}
     */
    public function ledger(Account $account, Request $request): JsonResponse
    {
        $this->authorize('view', $account);

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
