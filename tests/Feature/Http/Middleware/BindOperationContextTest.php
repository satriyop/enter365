<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\OperationContext;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('BindOperationContext Middleware', function () {
    it('binds OperationContext to container for authenticated requests', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/api/health')
            ->assertOk();

        // Context should be bound to container
        expect(app()->bound(OperationContext::class))->toBeTrue();

        $context = app(OperationContext::class);
        expect($context)->toBeInstanceOf(OperationContext::class);
        expect($context->userId)->toBe($user->id);
        expect($context->getSource())->toBe('http');
    });

    it('binds OperationContext with null userId for unauthenticated requests', function () {
        $this->get('/api/health')->assertOk();

        expect(app()->bound(OperationContext::class))->toBeTrue();

        $context = app(OperationContext::class);
        expect($context->userId)->toBeNull();
    });

    it('includes IP address in context', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/api/health', ['REMOTE_ADDR' => '192.168.1.100'])
            ->assertOk();

        $context = app(OperationContext::class);
        expect($context->ipAddress)->not->toBeNull();
    });

    it('includes request ID in metadata when provided', function () {
        $user = User::factory()->create();
        $requestId = 'test-request-123';

        $this->actingAs($user)
            ->withHeader('X-Request-ID', $requestId)
            ->get('/api/health')
            ->assertOk();

        $context = app(OperationContext::class);
        expect($context->getMetadata('request_id'))->toBe($requestId);
    });

    it('returns same instance throughout request lifecycle (scoped)', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/health')->assertOk();

        $context1 = app(OperationContext::class);
        $context2 = app(OperationContext::class);

        // Should be exact same instance (scoped singleton)
        expect($context1)->toBe($context2);
    });

    it('has null tenantId (placeholder for future multi-tenant)', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/health')->assertOk();

        $context = app(OperationContext::class);
        expect($context->tenantId)->toBeNull();
    });
});

describe('OperationContext Integration with Services', function () {
    it('services resolve context from container automatically', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/health')->assertOk();

        // Simulate what a service does
        $context = app(OperationContext::class);

        expect($context->userId)->toBe($user->id);
    });
});
