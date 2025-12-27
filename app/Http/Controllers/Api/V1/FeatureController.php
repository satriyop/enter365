<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Features;
use Illuminate\Http\JsonResponse;

class FeatureController extends Controller
{
    /**
     * Get feature flags status.
     *
     * Returns all feature modules with their enabled/disabled status.
     * Frontend applications can use this to conditionally render UI elements.
     *
     * GET /api/v1/features
     *
     * @return JsonResponse{
     *     modules: array<string, bool>,
     *     enabled: array<int, string>,
     *     disabled: array<int, string>
     * }
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'modules' => Features::all(),
            'enabled' => Features::enabledModules(),
            'disabled' => Features::disabledModules(),
        ]);
    }
}
