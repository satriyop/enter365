<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AssetDepreciationLineResource;
use App\Models\Accounting\AssetDepreciationLine;
use App\Models\Accounting\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepreciationScheduleController extends Controller
{
    /**
     * Review the depreciation board across assets (Odoo Review › Depreciation Schedule).
     *
     * @queryParam status string Filter by draft or posted. Example: draft
     * @queryParam from date Inclusive start date. Example: 2026-01-01
     * @queryParam to date Inclusive end date. Example: 2026-12-31
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FixedAsset::class);

        $query = AssetDepreciationLine::query()
            ->with('asset')
            ->orderBy('depreciation_date')
            ->orderBy('sequence');

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($from = $request->string('from')->trim()->toString()) {
            $query->whereDate('depreciation_date', '>=', $from);
        }

        if ($to = $request->string('to')->trim()->toString()) {
            $query->whereDate('depreciation_date', '<=', $to);
        }

        return AssetDepreciationLineResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }
}
