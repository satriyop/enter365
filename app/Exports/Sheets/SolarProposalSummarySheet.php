<?php

namespace App\Exports\Sheets;

use App\Models\Solar\SolarProposal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolarProposalSummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private SolarProposal $proposal
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $p = $this->proposal;

        return [
            ['SOLAR PROPOSAL - EXECUTIVE SUMMARY'],
            [''],
            ['Proposal Number', $p->proposal_number],
            ['Customer', $p->contact?->name ?? '-'],
            ['Site', $p->site_name],
            ['Location', "{$p->city}, {$p->province}"],
            ['Valid Until', $p->valid_until?->format('d M Y')],
            [''],
            ['SYSTEM SPECIFICATIONS'],
            ['System Capacity', $p->system_capacity_kwp.' kWp'],
            ['Annual Production', number_format($p->annual_production_kwh ?? 0).' kWh'],
            ['Monthly Production', number_format($p->monthly_production_kwh ?? 0).' kWh'],
            ['Solar Offset', ($p->solar_offset_percent ?? 0).'%'],
            [''],
            ['FINANCIAL METRICS'],
            ['Total Investment', 'Rp '.number_format($p->system_cost ?? 0, 0, ',', '.')],
            ['First Year Savings', 'Rp '.number_format($p->first_year_savings ?? 0, 0, ',', '.')],
            ['Payback Period', ($p->payback_years ?? 0).' years'],
            ['ROI (25 years)', ($p->roi_percent ?? 0).'%'],
            ['NPV', 'Rp '.number_format($p->npv ?? 0, 0, ',', '.')],
            ['IRR', ($p->irr_percent ?? 0).'%'],
            ['Total Lifetime Savings', 'Rp '.number_format($p->total_lifetime_savings ?? 0, 0, ',', '.')],
            [''],
            ['ENVIRONMENTAL IMPACT'],
            ['CO2 Offset (per year)', ($p->co2_offset_tons ?? 0).' tons'],
            ['Trees Equivalent', $p->trees_equivalent ?? 0],
            ['Cars Off Road Equivalent', $p->cars_equivalent ?? 0],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            9 => ['font' => ['bold' => true, 'size' => 12]],
            15 => ['font' => ['bold' => true, 'size' => 12]],
            24 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
