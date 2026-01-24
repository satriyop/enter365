<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('RequestIdMiddleware', function () {

    it('generates request ID when not provided', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk();

        // Should have X-Request-ID header in response
        expect($response->headers->has('X-Request-ID'))->toBeTrue();

        // Should be a valid UUID format
        $requestId = $response->headers->get('X-Request-ID');
        expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
    });

    it('uses provided request ID from client', function () {
        $customRequestId = 'custom-request-id-123';

        $response = $this->withHeader('X-Request-ID', $customRequestId)
            ->getJson('/api/health');

        $response->assertOk();

        // Should use the provided request ID
        expect($response->headers->get('X-Request-ID'))->toBe($customRequestId);
    });

    it('preserves request ID across redirect', function () {
        $customRequestId = 'preserved-request-id';

        $response = $this->withHeader('X-Request-ID', $customRequestId)
            ->getJson('/api/health/ready');

        $response->assertOk();
        expect($response->headers->get('X-Request-ID'))->toBe($customRequestId);
    });

    it('includes request ID on error responses', function () {
        // Try to access a protected route without auth - should still have request ID
        $response = $this->getJson('/api/v1/users');

        $response->assertUnauthorized();
        expect($response->headers->has('X-Request-ID'))->toBeTrue();
    });
});
