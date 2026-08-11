<?php

namespace App\Exports\Solar;

use App\Exports\Solar\Sheets\SolarProposalDetailsSheet;
use App\Exports\Solar\Sheets\SolarProposalProjectionsSheet;
use App\Exports\Solar\Sheets\SolarProposalSummarySheet;
use App\Models\Solar\SolarProposal;
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
            'Summary' => new SolarProposalSummarySheet($this->proposal),
            'Financial Projections' => new SolarProposalProjectionsSheet($this->proposal),
            'System Details' => new SolarProposalDetailsSheet($this->proposal),
        ];
    }
}
