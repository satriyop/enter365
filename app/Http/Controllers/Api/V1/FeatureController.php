<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Features;
use Illuminate\Http\JsonResponse;

class FeatureController extends Controller
{
    /**
     * Get feature flags status.
     *
     * Available to any authenticated user so the SPA can hide nav/routes
     * for disabled packs (odoo apps + industry add-ons: solar, electrical_panel).
     *
     * GET /api/v1/features
     */
    public function index(): JsonResponse
    {
        return $this->success([
            'preset' => config('features.preset'),
            'modules' => Features::all(),
            'enabled' => Features::enabledModules(),
            'disabled' => Features::disabledModules(),
        ]);
    }
}
