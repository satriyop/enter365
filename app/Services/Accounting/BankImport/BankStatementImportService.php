<?php

declare(strict_types=1);

namespace App\Services\Accounting\BankImport;

use App\Models\Accounting\BankTransaction;
use App\Models\Shared\Payment;
use App\Services\Base\Traits\WithOperationContext;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankStatementImportService
{
    use WithOperationContext;

    public function __construct(
        private BankStatementFormatDetector $detector
    ) {}

    /**
     * Detect the format of uploaded file.
     *
     * @return array{bank: string, date_format: string, delimiter: string, sample_rows: array<int, array<string, mixed>>}
     */
    public function detectFormat(UploadedFile $file): array
    {
        $result = $this->detector->detect($file);

        // Parse a few sample rows
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open file');
        }

        $sampleRows = [];
        try {
            // Skip header
            fgetcsv($handle, 0, $result['delimiter']);

            // Read up to 3 sample rows
            $rowNumber = 2;
            while (($row = fgetcsv($handle, 0, $result['delimiter'])) !== false && count($sampleRows) < 3) {
                try {
                    $parsed = $result['parser']->parseRow($row, $rowNumber);
                    $sampleRows[] = $parsed;
                    $rowNumber++;
                } catch (\Exception $e) {
                    // Skip invalid rows in sample
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'bank' => $result['bank'],
            'date_format' => $result['date_format'],
            'delimiter' => $result['delimiter'],
            'sample_rows' => $sampleRows,
        ];
    }

    /**
     * Validate import without saving to database.
     *
     * @return array{valid_count: int, error_count: int, duplicate_count: int, preview_rows: array<int, array<string, mixed>>, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateImport(UploadedFile $file, int $accountId): array
    {
        $result = $this->detector->detect($file);
        $parser = $result['parser'];
        $delimiter = $result['delimiter'];

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open file');
        }

        $validCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $previewRows = [];
        $errors = [];
        $warnings = [];

        // Get existing external IDs for duplicate detection
        $existingExternalIds = BankTransaction::where('account_id', $accountId)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->flip()
            ->all();

        try {
            // Skip header
            fgetcsv($handle, 0, $delimiter);

            $rowNumber = 2;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                // Skip empty rows
                if (count(array_filter($row, fn ($v) => trim($v) !== '')) === 0) {
                    continue;
                }

                try {
                    $parsed = $parser->parseRow($row, $rowNumber);

                    // Check for duplicate
                    if (isset($existingExternalIds[$parsed['external_id']])) {
                        $duplicateCount++;
                        $warnings[] = "Baris {$rowNumber}: Transaksi duplikat (sudah ada di database)";
                    } else {
                        $validCount++;
                    }

                    // Add to preview (max 10 rows)
                    if (count($previewRows) < 10) {
                        $previewRows[] = [
                            'row_number' => $rowNumber,
                            'is_duplicate' => isset($existingExternalIds[$parsed['external_id']]),
                            ...$parsed,
                        ];
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Baris {$rowNumber}: {$e->getMessage()}";
                }

                $rowNumber++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'duplicate_count' => $duplicateCount,
            'preview_rows' => $previewRows,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Execute import and save to database.
     *
     * @return array{import_batch: string, imported_count: int, skipped_count: int, errors: array<int, string>}
     */
    public function executeImport(UploadedFile $file, int $accountId): array
    {
        $result = $this->detector->detect($file);
        $parser = $result['parser'];
        $delimiter = $result['delimiter'];

        $importBatch = (string) Str::uuid();
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

        // Get existing external IDs for duplicate detection
        $existingExternalIds = BankTransaction::where('account_id', $accountId)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->flip()
            ->all();

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open file');
        }

        try {
            DB::beginTransaction();

            // Skip header
            fgetcsv($handle, 0, $delimiter);

            $rowNumber = 2;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                // Skip empty rows
                if (count(array_filter($row, fn ($v) => trim($v) !== '')) === 0) {
                    continue;
                }

                try {
                    $parsed = $parser->parseRow($row, $rowNumber);

                    // Skip duplicates
                    if (isset($existingExternalIds[$parsed['external_id']])) {
                        $skippedCount++;

                        continue;
                    }

                    // Create transaction
                    BankTransaction::create([
                        'account_id' => $accountId,
                        'transaction_date' => $parsed['transaction_date'],
                        'description' => $parsed['description'],
                        'reference' => $parsed['reference'],
                        'debit' => $parsed['debit'],
                        'credit' => $parsed['credit'],
                        'balance' => $parsed['balance'],
                        'status' => 'unmatched',
                        'import_batch' => $importBatch,
                        'external_id' => $parsed['external_id'],
                        'created_by' => $this->getUserId(),
                    ]);

                    $importedCount++;

                    // Add to existing IDs to prevent duplicates within same file
                    $existingExternalIds[$parsed['external_id']] = true;
                } catch (\Exception $e) {
                    $errors[] = "Baris {$rowNumber}: {$e->getMessage()}";
                }

                $rowNumber++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return [
            'import_batch' => $importBatch,
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Suggest matches for transactions in a batch.
     *
     * @return array<int, array{transaction_id: int, matches: array<int, array{type: string, id: int, confidence: int, reason: string}>}>
     */
    public function suggestMatchesForBatch(string $importBatch, int $accountId): array
    {
        $transactions = BankTransaction::where('import_batch', $importBatch)
            ->where('account_id', $accountId)
            ->where('status', 'unmatched')
            ->get();

        $suggestions = [];
        $dateTolerance = config('accounting.bank_import.match_date_tolerance_days', 7);

        foreach ($transactions as $txn) {
            $matches = [];
            $amount = $txn->getNetAmount();
            $isDebit = $txn->debit > 0;

            // Match by reference first (highest confidence)
            if ($txn->reference) {
                $payment = Payment::where('cash_account_id', $accountId)
                    ->where('payment_number', $txn->reference)
                    ->where('is_voided', false)
                    ->whereDoesntHave('bankTransaction')
                    ->first();

                if ($payment) {
                    $matches[] = [
                        'type' => 'payment',
                        'id' => $payment->id,
                        'number' => $payment->payment_number,
                        'amount' => $payment->amount,
                        'date' => Carbon::parse($payment->payment_date)->toDateString(),
                        'confidence' => 99,
                        'reason' => 'Reference match',
                    ];
                }
            }

            // Match by exact amount and date within tolerance
            if (empty($matches)) {
                $startDate = Carbon::parse($txn->transaction_date)->subDays($dateTolerance);
                $endDate = Carbon::parse($txn->transaction_date)->addDays($dateTolerance);

                $payment = Payment::where('cash_account_id', $accountId)
                    ->where('amount', abs($amount))
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->where('is_voided', false)
                    ->whereDoesntHave('bankTransaction')
                    ->first();

                if ($payment) {
                    $matches[] = [
                        'type' => 'payment',
                        'id' => $payment->id,
                        'number' => $payment->payment_number,
                        'amount' => $payment->amount,
                        'date' => Carbon::parse($payment->payment_date)->toDateString(),
                        'confidence' => 85,
                        'reason' => 'Amount and date match',
                    ];
                }
            }

            if (! empty($matches)) {
                $suggestions[] = [
                    'transaction_id' => $txn->id,
                    'transaction_date' => Carbon::parse($txn->transaction_date)->toDateString(),
                    'transaction_amount' => $amount,
                    'matches' => $matches,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Delete all unreconciled transactions in a batch.
     */
    public function deleteBatch(string $importBatch): int
    {
        // Check if any transactions are reconciled
        $reconciled = BankTransaction::where('import_batch', $importBatch)
            ->where('status', 'reconciled')
            ->exists();

        if ($reconciled) {
            throw new \RuntimeException('Tidak dapat menghapus batch yang memiliki transaksi yang sudah direkonsiliasi');
        }

        return BankTransaction::where('import_batch', $importBatch)->delete();
    }
}
