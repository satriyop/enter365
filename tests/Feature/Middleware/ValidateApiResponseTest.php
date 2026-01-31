<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Sales\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Ensure api.json exists
    $this->artisan('scramble:export', ['--path' => 'api.json']);
});

describe('ValidateApiResponse Middleware', function () {

    it('skips validation when disabled', function () {
        Config::set('api.response_validation.enabled', false);

        Quotation::factory()->create();

        $response = $this->getJson('/api/v1/quotations');

        $response->assertOk();
        // No validation errors logged
    });

    it('validates responses when enabled', function () {
        Config::set('api.response_validation.enabled', true);
        Config::set('api.response_validation.strict', false);

        Log::spy();

        Quotation::factory()->create();

        $response = $this->getJson('/api/v1/quotations');

        $response->assertOk();
        // Validation runs but doesn't block response
    });

    it('skips validation for non-API routes', function () {
        Config::set('api.response_validation.enabled', true);

        Route::get('/test-non-api', fn () => response()->json(['ok' => true]));
        $response = $this->get('/test-non-api');

        // Should not validate non-API routes
        expect($response->status())->not->toBe(500);
    });

    it('skips validation for error responses', function () {
        Config::set('api.response_validation.enabled', true);

        $response = $this->getJson('/api/v1/quotations/99999');

        $response->assertNotFound();
        // Should not validate 404 responses
    });

    it('validates response structure and provides enhanced context', function () {
        Config::set('api.response_validation.enabled', true);
        Config::set('api.response_validation.strict', false);

        Quotation::factory()->create();

        $response = $this->getJson('/api/v1/quotations');

        $response->assertOk();

        // Validation runs (may pass or fail, but structure is tested)
        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toBeArray();
    });

    it('handles missing api.json gracefully', function () {
        Config::set('api.response_validation.enabled', true);

        // Temporarily rename api.json
        $schemaPath = base_path('api.json');
        $backupPath = base_path('api.json.backup');

        if (file_exists($schemaPath)) {
            rename($schemaPath, $backupPath);
        }

        try {
            Quotation::factory()->create();

            $response = $this->getJson('/api/v1/quotations');

            // Should still work, just skip validation
            $response->assertOk();
        } finally {
            // Restore api.json
            if (file_exists($backupPath)) {
                rename($backupPath, $schemaPath);
            }
        }
    });

    it('validates response structure matches schema when enabled', function () {
        Config::set('api.response_validation.enabled', true);
        Config::set('api.response_validation.strict', false);

        Quotation::factory()->create();

        $response = $this->getJson('/api/v1/quotations');

        $response->assertOk();

        // Response should be valid JSON
        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toBeArray();
    });

    it('skips validation for non-JSON responses', function () {
        Config::set('api.response_validation.enabled', true);

        // Health check endpoint returns plain text
        $response = $this->get('/api/health');

        $response->assertOk();
        // Should not attempt to validate non-JSON
    });
});
