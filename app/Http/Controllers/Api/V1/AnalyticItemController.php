<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AnalyticDimensionServiceInterface;
use App\Http\Resources\Api\V1\AnalyticItemResource;
use App\Models\Accounting\AnalyticAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnalyticItemController extends Controller
{
    public function __construct(
        private AnalyticDimensionServiceInterface $analytics,
    ) {}

    /**
     * Browse analytic items generated from posted journal distributions (Odoo Accounting › Analytic Items).
     *
     * @queryParam analytic_account_id int Filter by analytic account. Example: 2
     * @queryParam from date Inclusive start date. Example: 2026-01-01
     * @queryParam to date Inclusive end date. Example: 2026-12-31
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnalyticAccount::class);

        $items = $this->analytics->items([
            'analytic_account_id' => $request->integer('analytic_account_id') ?: null,
            'from' => $request->string('from')->trim()->toString() ?: null,
            'to' => $request->string('to')->trim()->toString() ?: null,
            'page' => $request->integer('page', 1),
            'per_page' => $request->integer('per_page', 50),
        ]);

        return AnalyticItemResource::collection($items);
    }
}
