<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FeatureController extends Controller
{
    /**
     * Get feature flags status.
     *
     * Returns all feature modules with their enabled/disabled status.
     * Frontend applications can use this to conditionally render UI elements.
     *
     * GET /api/v1/features
     */
    public function index(): JsonResponse
    {
        Gate::authorize('settings.features');

        return $this->success([
            'modules' => Features::all(),
            'enabled' => Features::enabledModules(),
            'disabled' => Features::disabledModules(),
        ]);
    }
}
