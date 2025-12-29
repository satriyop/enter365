<?php

namespace App\Exports\Sheets;

use App\Models\Accounting\SolarProposal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolarProposalProjectionsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private SolarProposal $proposal
    ) {}

    public function title(): string
    {
        return 'Financial Projections';
    }

    public function headings(): array
    {
        return [
            'Year',
            'Annual Savings (Rp)',
            'Cumulative Savings (Rp)',
            'Electricity Rate (Rp/kWh)',
            'Annual Production (kWh)',
            'Degradation Factor',
        ];
    }

    public function array(): array
    {
        $financialAnalysis = $this->proposal->financial_analysis;

        if (! $financialAnalysis || ! isset($financialAnalysis['yearly_projections'])) {
            return [['No financial projections available']];
        }

        $rows = [];
        $annualProduction = $this->proposal->annual_production_kwh ?? 0;

        foreach ($financialAnalysis['yearly_projections'] as $projection) {
            $year = $projection['year'] ?? 0;
            $degradationFactor = pow(0.995, $year); // 0.5% annual degradation

            $rows[] = [
                $year,
                $projection['savings'] ?? 0,
                $projection['cumulative_savings'] ?? 0,
                $projection['electricity_rate'] ?? 0,
                round($annualProduction * $degradationFactor),
                number_format($degradationFactor * 100, 2).'%',
            ];
        }

        // Add totals row
        $rows[] = [];
        $rows[] = [
            'TOTAL',
            array_sum(array_column($financialAnalysis['yearly_projections'], 'savings')),
            '',
            '',
            '',
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->proposal->financial_analysis['yearly_projections'] ?? []) + 3;

        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'F97316'],
            ], 'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }
}
