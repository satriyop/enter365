<?php

declare(strict_types=1);

namespace App\Services\Accounting\BankImport\Contracts;

interface BankStatementParserInterface
{
    /**
     * Check if this parser can handle the given CSV headers and first row.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $firstRow
     */
    public function canParse(array $headers, array $firstRow): bool;

    /**
     * Get unique identifier for this bank.
     */
    public function getBankIdentifier(): string;

    /**
     * Parse a single row into normalized format.
     *
     * @param  array<int, string>  $row
     * @return array{transaction_date: string, description: string, reference: string|null, debit: int, credit: int, balance: int|null, external_id: string}
     *
     * @throws \InvalidArgumentException if row cannot be parsed
     */
    public function parseRow(array $row, int $rowNumber): array;

    /**
     * Get the date format used by this bank.
     */
    public function getDateFormat(): string;
}
