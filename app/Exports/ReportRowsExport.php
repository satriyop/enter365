<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportRowsExport implements FromArray, WithHeadings
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private array $rows,
        private array $headers,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_values($this->headers);
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_map(function (array $row): array {
            $csvRow = [];
            foreach (array_keys($this->headers) as $key) {
                $value = $row[$key] ?? '';
                $csvRow[] = $value instanceof \BackedEnum ? $value->value : $value;
            }

            return $csvRow;
        }, $this->rows);
    }
}
