<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExecuteBankStatementImportRequest;
use App\Http\Requests\Api\V1\ValidateBankStatementImportRequest;
use App\Models\Accounting\BankTransaction;
use App\Services\Accounting\BankImport\BankStatementImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankStatementImportController extends Controller
{
    public function __construct(
        private BankStatementImportService $importService
    ) {}

    /**
     * Detect format of uploaded bank statement.
     */
    public function detectFormat(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.required' => 'File CSV wajib diupload.',
            'file.mimes' => 'File harus berformat CSV atau TXT.',
        ]);

        try {
            $result = $this->importService->detectFormat($request->file('file'));

            return response()->json([
                'message' => 'Format berhasil terdeteksi.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mendeteksi format: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Validate import without saving.
     */
    public function validate(ValidateBankStatementImportRequest $request): JsonResponse
    {
        $this->authorize('create', BankTransaction::class);

        try {
            $result = $this->importService->validateImport(
                $request->file('file'),
                (int) $request->input('account_id')
            );

            return response()->json([
                'message' => 'Validasi selesai.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memvalidasi file: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Execute import and save to database.
     */
    public function import(ExecuteBankStatementImportRequest $request): JsonResponse
    {
        $this->authorize('create', BankTransaction::class);

        try {
            $result = $this->importService->executeImport(
                $request->file('file'),
                (int) $request->input('account_id')
            );

            $message = "{$result['imported_count']} transaksi berhasil diimpor";
            if ($result['skipped_count'] > 0) {
                $message .= ", {$result['skipped_count']} transaksi dilewati (duplikat)";
            }
            $message .= '.';

            return response()->json([
                'message' => $message,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengimpor file: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * List all import batches.
     */
    public function listBatches(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankTransaction::class);

        $accountId = $request->input('account_id');

        $query = BankTransaction::query()
            ->select('import_batch', 'account_id')
            ->selectRaw('MIN(transaction_date) as first_date')
            ->selectRaw('MAX(transaction_date) as last_date')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(debit) as total_debit')
            ->selectRaw('SUM(credit) as total_credit')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as reconciled_count', ['reconciled'])
            ->whereNotNull('import_batch')
            ->groupBy('import_batch', 'account_id')
            ->orderByDesc('first_date');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $batches = $query->get();

        return response()->json([
            'data' => $batches,
        ]);
    }

    /**
     * Suggest matches for transactions in a batch.
     */
    public function suggestMatches(Request $request, string $batch): JsonResponse
    {
        $this->authorize('viewAny', BankTransaction::class);

        $accountId = $request->input('account_id');

        if (! $accountId) {
            return response()->json([
                'message' => 'account_id wajib diisi.',
            ], 422);
        }

        try {
            $suggestions = $this->importService->suggestMatchesForBatch($batch, (int) $accountId);

            return response()->json([
                'message' => count($suggestions).' saran match ditemukan.',
                'data' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat saran match: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete an import batch.
     */
    public function deleteBatch(string $batch): JsonResponse
    {
        $this->authorize('delete', new BankTransaction);

        try {
            $count = $this->importService->deleteBatch($batch);

            return response()->json([
                'message' => "{$count} transaksi berhasil dihapus.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
