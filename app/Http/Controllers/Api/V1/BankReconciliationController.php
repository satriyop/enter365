<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BankTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReconcileBankTransactionRequest;
use App\Http\Requests\Api\V1\StoreBankTransactionRequest;
use App\Http\Resources\Api\V1\BankTransactionResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use App\Models\Shared\Payment;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    public function __construct(
        private BankReconciliationService $reconciliationService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BankTransaction::query()->with(['account', 'matchedPayment']);

        if ($request->has('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->input('end_date'));
        }

        if ($request->has('import_batch')) {
            $query->where('import_batch', $request->input('import_batch'));
        }

        $transactions = $query->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 50));

        return BankTransactionResource::collection($transactions);
    }

    public function store(StoreBankTransactionRequest $request): JsonResponse
    {
        $transaction = $this->reconciliationService->create($request->validated());

        return (new BankTransactionResource($transaction->load('account')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(BankTransaction $bankTransaction): BankTransactionResource
    {
        return new BankTransactionResource(
            $bankTransaction->load(['account', 'matchedPayment', 'matchedJournalLine.journalEntry'])
        );
    }

    public function destroy(BankTransaction $bankTransaction): JsonResponse
    {
        try {
            $this->reconciliationService->delete($bankTransaction);

            return response()->json(['message' => 'Transaksi bank berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function matchToPayment(BankTransaction $bankTransaction, Payment $payment): JsonResponse
    {
        if ($bankTransaction->status !== BankTransactionStatus::Unmatched) {
            return response()->json([
                'message' => 'Transaksi sudah di-match atau direkonsiliasi.',
            ], 422);
        }

        $bankTransaction->matchToPayment($payment);

        return response()->json([
            'message' => 'Transaksi berhasil di-match dengan pembayaran.',
            'data' => new BankTransactionResource($bankTransaction->fresh(['account', 'matchedPayment'])),
        ]);
    }

    public function unmatch(BankTransaction $bankTransaction): JsonResponse
    {
        if ($bankTransaction->isReconciled()) {
            return response()->json([
                'message' => 'Tidak dapat unmatch transaksi yang sudah direkonsiliasi.',
            ], 422);
        }

        $bankTransaction->unmatch();

        return response()->json([
            'message' => 'Transaksi berhasil di-unmatch.',
            'data' => new BankTransactionResource($bankTransaction->fresh('account')),
        ]);
    }

    public function reconcile(ReconcileBankTransactionRequest $request, BankTransaction $bankTransaction): JsonResponse
    {
        if ($bankTransaction->isReconciled()) {
            return response()->json([
                'message' => 'Transaksi sudah direkonsiliasi.',
            ], 422);
        }

        $bankTransaction->reconcile(auth()->id());

        return response()->json([
            'message' => 'Transaksi berhasil direkonsiliasi.',
            'data' => new BankTransactionResource($bankTransaction->fresh(['account', 'matchedPayment'])),
        ]);
    }

    public function bulkReconcile(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:bank_transactions,id'],
        ]);

        $count = DB::transaction(function () use ($request) {
            $transactions = BankTransaction::whereIn('id', $request->transaction_ids)
                ->where('status', '!=', BankTransactionStatus::Reconciled)
                ->get();

            foreach ($transactions as $transaction) {
                $transaction->reconcile(auth()->id());
            }

            return $transactions->count();
        });

        return response()->json([
            'message' => "{$count} transaksi berhasil direkonsiliasi.",
            'reconciled_count' => $count,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $accountId = $request->input('account_id');

        $query = BankTransaction::query();

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $summary = [
            'total_transactions' => (clone $query)->count(),
            'unmatched' => (clone $query)->where('status', BankTransactionStatus::Unmatched)->count(),
            'matched' => (clone $query)->where('status', BankTransactionStatus::Matched)->count(),
            'reconciled' => (clone $query)->where('status', BankTransactionStatus::Reconciled)->count(),
            'total_debits' => (clone $query)->sum('debit'),
            'total_credits' => (clone $query)->sum('credit'),
        ];

        if ($accountId) {
            $account = Account::find($accountId);
            $summary['account'] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ];
        }

        return response()->json($summary);
    }

    public function suggestMatches(BankTransaction $bankTransaction): JsonResponse
    {
        if ($bankTransaction->status !== BankTransactionStatus::Unmatched) {
            return response()->json([
                'message' => 'Transaksi sudah di-match.',
                'suggestions' => [],
            ]);
        }

        $amount = $bankTransaction->debit > 0 ? $bankTransaction->debit : $bankTransaction->credit;
        $isDebit = $bankTransaction->debit > 0;

        // Suggest payments with matching amounts
        $payments = Payment::query()
            ->where('amount', $amount)
            ->where('is_voided', false)
            ->whereDoesntHave('bankTransaction')
            ->orderByDesc('payment_date')
            ->limit(10)
            ->get();

        $suggestions = $payments->map(fn ($payment) => [
            'type' => 'payment',
            'id' => $payment->id,
            'number' => $payment->payment_number,
            'amount' => $payment->amount,
            'date' => $payment->payment_date->toDateString(),
            'description' => $payment->description,
        ]);

        return response()->json([
            'transaction' => new BankTransactionResource($bankTransaction),
            'suggestions' => $suggestions,
        ]);
    }
}
