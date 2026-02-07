<?php

declare(strict_types=1);

namespace App\Services\Accounting\BankImport\Parsers;

use App\Services\Accounting\BankImport\Contracts\BankStatementParserInterface;
use Carbon\Carbon;

class BniBankStatementParser implements BankStatementParserInterface
{
    private const REQUIRED_HEADERS = ['Date', 'Description', 'Debit', 'Credit', 'Balance'];

    public function canParse(array $headers, array $firstRow): bool
    {
        $normalizedHeaders = array_map('trim', $headers);

        foreach (self::REQUIRED_HEADERS as $required) {
            $found = false;
            foreach ($normalizedHeaders as $header) {
                if (stripos($header, $required) !== false) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    public function getBankIdentifier(): string
    {
        return 'bni';
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d';
    }

    public function parseRow(array $row, int $rowNumber): array
    {
        if (count($row) < 5) {
            throw new \InvalidArgumentException("Row {$rowNumber}: Insufficient columns for BNI format");
        }

        $dateStr = trim($row[0]);
        $description = trim($row[1]);
        $reference = isset($row[2]) && ! empty(trim($row[2])) ? trim($row[2]) : null;
        $debitStr = trim($row[3]);
        $creditStr = trim($row[4]);
        $balanceStr = isset($row[5]) ? trim($row[5]) : '';

        // Parse date
        try {
            $date = Carbon::createFromFormat($this->getDateFormat(), $dateStr);
            $transactionDate = $date->toDateString();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Row {$rowNumber}: Invalid date format '{$dateStr}'");
        }

        // Parse amounts
        $debit = $this->parseAmount($debitStr);
        $credit = $this->parseAmount($creditStr);
        $balance = $this->parseAmount($balanceStr);

        // Generate external_id
        $externalId = $this->generateExternalId($transactionDate, $description, $debit, $credit);

        return [
            'transaction_date' => $transactionDate,
            'description' => $description,
            'reference' => $reference,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $balance,
            'external_id' => $externalId,
        ];
    }

    private function parseAmount(string $amount): int
    {
        // Remove currency symbols, whitespace
        $cleaned = preg_replace('/[^\d,.-]/', '', $amount);

        if ($cleaned === null || $cleaned === '') {
            return 0;
        }

        // Remove commas (thousand separator)
        $cleaned = str_replace(',', '', $cleaned);

        // Convert to float then to cents
        $value = (float) $cleaned;

        return (int) round($value);
    }

    private function generateExternalId(string $date, string $description, int $debit, int $credit): string
    {
        return md5($date.$description.$debit.$credit);
    }
}
