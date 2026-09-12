<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\Projects\TaskAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskAnalysisController extends Controller
{
    public function __construct(
        private TaskAnalysisService $analysis,
    ) {}

    /**
     * Tasks analysis across projects (Odoo Project › Reporting › Tasks Analysis).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     */
    public function tasksAnalysis(Request $request): JsonResponse
    {
        $this->authorize('reports.project');

        $from = $request->string('from')->trim()->toString();
        $to = $request->string('to')->trim()->toString();

        return $this->success($this->analysis->tasksAnalysis(
            $from === '' ? null : $from,
            $to === '' ? null : $to,
        ));
    }

    /**
     * Customer ratings summary (Odoo Project › Customer Ratings).
     *
     * @queryParam project_id int Restrict to one project. Example: 1
     */
    public function customerRatings(Request $request): JsonResponse
    {
        $this->authorize('reports.project');

        $projectId = $request->integer('project_id');

        return $this->success($this->analysis->customerRatings($projectId > 0 ? $projectId : null));
    }
}
