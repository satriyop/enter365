<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tax\EfakturExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EfakturExportController extends Controller
{
    public function __construct(
        private EfakturExportService $exportService
    ) {}

    /**
     * Export e-Faktur CSV file.
     *
     * @queryParam start_date string required Start date (Y-m-d). Example: 2026-01-01
     * @queryParam end_date string required End date (Y-m-d). Example: 2026-01-31
     */
    public function export(Request $request): Response
    {
        Gate::authorize('reports.tax');

        $request->validate([
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $csv = $this->exportService->generateCsv(
            $request->input('start_date'),
            $request->input('end_date')
        );

        if ($csv === '') {
            return response('', 204);
        }

        $filename = sprintf(
            'efaktur_%s_%s.csv',
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
