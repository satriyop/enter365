<?php

namespace App\Exports;

use App\Models\Accounting\SolarProposal;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SolarProposalExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private SolarProposal $proposal
    ) {}

    public function sheets(): array
    {
        return [
            'Summary' => new Sheets\SolarProposalSummarySheet($this->proposal),
            'Financial Projections' => new Sheets\SolarProposalProjectionsSheet($this->proposal),
            'System Details' => new Sheets\SolarProposalDetailsSheet($this->proposal),
        ];
    }
}
