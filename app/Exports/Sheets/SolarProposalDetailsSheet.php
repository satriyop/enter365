<?php

namespace App\Exports\Sheets;

use App\Models\Accounting\SolarProposal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolarProposalDetailsSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private SolarProposal $proposal
    ) {}

    public function title(): string
    {
        return 'System Details';
    }

    public function array(): array
    {
        $p = $this->proposal;

        return [
            ['SITE INFORMATION'],
            [''],
            ['Site Name', $p->site_name],
            ['Address', $p->site_address],
            ['City', $p->city],
            ['Province', $p->province],
            ['Coordinates', $p->latitude && $p->longitude ? "{$p->latitude}, {$p->longitude}" : '-'],
            ['Roof Area', $p->roof_area_m2 ? number_format($p->roof_area_m2).' m²' : '-'],
            ['Roof Type', $p->roof_type_label ?? $p->roof_type],
            ['Roof Orientation', $p->roof_orientation_label ?? $p->roof_orientation],
            ['Roof Tilt', $p->roof_tilt_angle ? $p->roof_tilt_angle.'°' : '-'],
            ['Shading Factor', $p->shading_factor ? ($p->shading_factor * 100).'%' : '-'],
            [''],
            ['ELECTRICITY PROFILE'],
            [''],
            ['Monthly Consumption', number_format($p->monthly_consumption_kwh ?? 0).' kWh'],
            ['Annual Consumption', number_format(($p->monthly_consumption_kwh ?? 0) * 12).' kWh'],
            ['PLN Tariff Category', $p->pln_tariff_category ?? '-'],
            ['Electricity Rate', 'Rp '.number_format($p->electricity_rate ?? 0, 0, ',', '.').'/kWh'],
            ['Tariff Escalation', ($p->tariff_escalation_percent ?? 0).'% per year'],
            [''],
            ['SOLAR DATA'],
            [''],
            ['Peak Sun Hours', ($p->peak_sun_hours ?? 0).' hours/day'],
            ['Solar Irradiance', ($p->solar_irradiance ?? 0).' kWh/m²/day'],
            ['Performance Ratio', ($p->performance_ratio ? ($p->performance_ratio * 100) : 80).'%'],
            [''],
            ['SELECTED SYSTEM'],
            [''],
            ['BOM/Package', $p->selectedBom?->name ?? 'Auto-select'],
            ['System Cost', 'Rp '.number_format($p->system_cost ?? 0, 0, ',', '.')],
            ['Cost per kWp', $p->system_capacity_kwp && $p->system_cost
                ? 'Rp '.number_format($p->system_cost / $p->system_capacity_kwp, 0, ',', '.')
                : '-'],
            [''],
            ['ASSUMPTIONS'],
            [''],
            ['System Lifetime', '25 years'],
            ['Panel Degradation', '0.5% per year'],
            ['Discount Rate', '10%'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            14 => ['font' => ['bold' => true, 'size' => 12]],
            22 => ['font' => ['bold' => true, 'size' => 12]],
            29 => ['font' => ['bold' => true, 'size' => 12]],
            35 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
