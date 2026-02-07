<?php

declare(strict_types=1);

namespace App\Services\Accounting\BankImport\Parsers;

use App\Services\Accounting\BankImport\Contracts\BankStatementParserInterface;
use Carbon\Carbon;

class GenericBankStatementParser implements BankStatementParserInterface
{
    public function canParse(array $headers, array $firstRow): bool
    {
        // Generic parser always returns true as fallback
        return true;
    }

    public function getBankIdentifier(): string
    {
        return 'generic';
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d';
    }

    public function parseRow(array $row, int $rowNumber): array
    {
        if (count($row) < 3) {
            throw new \InvalidArgumentException("Row {$rowNumber}: Insufficient columns (minimum 3: date, description, amount)");
        }

        $dateStr = trim($row[0]);
        $description = trim($row[1]);

        // Try to parse date with multiple formats
        $transactionDate = $this->parseDate($dateStr, $rowNumber);

        // Try to determine debit/credit columns
        $debit = 0;
        $credit = 0;
        $balance = null;

        if (count($row) >= 5) {
            // Assume format: Date, Description, Debit, Credit, Balance
            $debit = $this->parseAmount(trim($row[2]));
            $credit = $this->parseAmount(trim($row[3]));
            $balance = $this->parseAmount(trim($row[4]));
        } elseif (count($row) >= 4) {
            // Assume format: Date, Description, Amount, Balance
            $amount = $this->parseAmount(trim($row[2]));
            $balance = $this->parseAmount(trim($row[3]));

            if ($amount < 0) {
                $debit = abs($amount);
            } else {
                $credit = $amount;
            }
        } else {
            // Format: Date, Description, Amount
            $amount = $this->parseAmount(trim($row[2]));

            if ($amount < 0) {
                $debit = abs($amount);
            } else {
                $credit = $amount;
            }
        }

        // Generate external_id
        $externalId = $this->generateExternalId($transactionDate, $description, $debit, $credit);

        return [
            'transaction_date' => $transactionDate,
            'description' => $description,
            'reference' => null,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $balance,
            'external_id' => $externalId,
        ];
    }

    private function parseDate(string $dateStr, int $rowNumber): string
    {
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
            'm-d-Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateStr);

                return $date->toDateString();
            } catch (\Exception $e) {
                // Try next format
            }
        }

        throw new \InvalidArgumentException("Row {$rowNumber}: Could not parse date '{$dateStr}'");
    }

    private function parseAmount(string $amount): int
    {
        // Remove currency symbols, whitespace
        $cleaned = preg_replace('/[^\d,.-]/', '', $amount);

        if ($cleaned === null || $cleaned === '') {
            return 0;
        }

        // Determine if comma or dot is decimal separator
        $lastComma = strrpos($cleaned, ',');
        $lastDot = strrpos($cleaned, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Both present - whichever comes last is decimal separator
            if ($lastComma > $lastDot) {
                // Comma is decimal separator
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // Dot is decimal separator
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($lastComma !== false) {
            // Only comma - assume decimal separator
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // If only dot, keep as is

        // Convert to integer
        $value = (float) $cleaned;

        return (int) round($value);
    }

    private function generateExternalId(string $date, string $description, int $debit, int $credit): string
    {
        return md5($date.$description.$debit.$credit);
    }
}
