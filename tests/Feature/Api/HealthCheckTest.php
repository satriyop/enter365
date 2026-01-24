<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Health Check Endpoints', function () {

    it('returns healthy status when all checks pass', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['status'],
                    'cache' => ['status'],
                    'storage' => ['status'],
                ],
                'version',
            ])
            ->assertJson(['status' => 'healthy']);

        // Verify all checks passed
        $checks = $response->json('checks');
        expect($checks['database']['status'])->toBe('ok');
        expect($checks['cache']['status'])->toBe('ok');
        expect($checks['storage']['status'])->toBe('ok');
    });

    it('returns ready status', function () {
        $response = $this->getJson('/api/health/ready');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
            ])
            ->assertJson(['status' => 'ready']);
    });

    it('returns live status', function () {
        $response = $this->getJson('/api/health/live');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
            ])
            ->assertJson(['status' => 'live']);
    });

    it('includes database latency in health check', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $checks = $response->json('checks');

        expect($checks['database'])->toHaveKey('latency_ms');
        expect($checks['database']['latency_ms'])->toBeNumeric();
    });

    it('includes cache latency in health check', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $checks = $response->json('checks');

        expect($checks['cache'])->toHaveKey('latency_ms');
        expect($checks['cache']['latency_ms'])->toBeNumeric();
    });

    it('includes version in health check', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        expect($response->json('version'))->not->toBeEmpty();
    });

    it('includes ISO8601 timestamp', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $timestamp = $response->json('timestamp');

        // Validate ISO8601 format
        $parsed = \Carbon\Carbon::parse($timestamp);
        expect($parsed)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    it('does not require authentication', function () {
        // Health endpoints should be accessible without auth
        $response = $this->getJson('/api/health');
        $response->assertOk();

        $response = $this->getJson('/api/health/ready');
        $response->assertOk();

        $response = $this->getJson('/api/health/live');
        $response->assertOk();
    });
});
