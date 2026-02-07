<?php

declare(strict_types=1);

namespace App\Services\Accounting\BankImport\Parsers;

use App\Services\Accounting\BankImport\Contracts\BankStatementParserInterface;
use Carbon\Carbon;

class MandiriBankStatementParser implements BankStatementParserInterface
{
    private const REQUIRED_HEADERS = ['Tanggal', 'Keterangan', 'Debet', 'Kredit', 'Saldo'];

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
        return 'mandiri';
    }

    public function getDateFormat(): string
    {
        return 'd-m-Y';
    }

    public function parseRow(array $row, int $rowNumber): array
    {
        if (count($row) < 5) {
            throw new \InvalidArgumentException("Row {$rowNumber}: Insufficient columns for Mandiri format");
        }

        $dateStr = trim($row[0]);
        $description = trim($row[1]);
        $debetStr = trim($row[2]);
        $kreditStr = trim($row[3]);
        $saldoStr = trim($row[4]);

        // Parse date
        try {
            $date = Carbon::createFromFormat($this->getDateFormat(), $dateStr);
            $transactionDate = $date->toDateString();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Row {$rowNumber}: Invalid date format '{$dateStr}'");
        }

        // Parse amounts
        $debit = $this->parseAmount($debetStr);
        $credit = $this->parseAmount($kreditStr);
        $balance = $this->parseAmount($saldoStr);

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

    private function parseAmount(string $amount): int
    {
        // Remove currency symbols, whitespace
        $cleaned = preg_replace('/[^\d,.-]/', '', $amount);

        if ($cleaned === null || $cleaned === '') {
            return 0;
        }

        // Remove dots (thousand separator)
        $cleaned = str_replace('.', '', $cleaned);

        // Replace comma with dot (decimal separator)
        $cleaned = str_replace(',', '.', $cleaned);

        // Convert to integer (IDR cents)
        $value = (float) $cleaned;

        return (int) round($value);
    }

    private function generateExternalId(string $date, string $description, int $debit, int $credit): string
    {
        return md5($date.$description.$debit.$credit);
    }
}
