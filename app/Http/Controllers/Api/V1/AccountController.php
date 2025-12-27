<?php

namespace App\Http\Controllers\Api\V1;

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

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Account::query()
            ->with($request->boolean('include_children') ? ['parent', 'children'] : ['parent']);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
            });
        }

        $accounts = $query->orderBy('code')->paginate($request->input('per_page', 50));

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $account = Account::create($request->validated());

        return new AccountResource($account->load('parent'));
    }

    public function show(Account $account): AccountResource
    {
        return new AccountResource($account->load(['parent', 'children']));
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
