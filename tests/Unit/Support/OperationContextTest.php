<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\OperationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('OperationContext', function () {
    describe('fromAuth', function () {
        it('creates context from authenticated user', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $context = OperationContext::fromAuth();

            expect($context->userId)->toBe($user->id)
                ->and($context->timestamp)->not->toBeNull()
                ->and($context->isSystem())->toBeFalse();
        });

        it('creates context with null user when not authenticated', function () {
            $context = OperationContext::fromAuth();

            expect($context->userId)->toBeNull()
                ->and($context->isSystem())->toBeTrue();
        });
    });

    describe('system', function () {
        it('creates system context with null user', function () {
            $context = OperationContext::system();

            expect($context->userId)->toBeNull()
                ->and($context->isSystem())->toBeTrue()
                ->and($context->getSource())->toBe('system')
                ->and($context->hasMetadata('source'))->toBeTrue();
        });
    });

    describe('forUser', function () {
        it('creates context for specific user', function () {
            $context = OperationContext::forUser(42);

            expect($context->userId)->toBe(42)
                ->and($context->isSystem())->toBeFalse()
                ->and($context->timestamp)->not->toBeNull();
        });
    });

    describe('forJob', function () {
        it('creates context for job without dispatcher', function () {
            $context = OperationContext::forJob();

            expect($context->userId)->toBeNull()
                ->and($context->getSource())->toBe('job');
        });

        it('creates context for job with dispatcher', function () {
            $context = OperationContext::forJob(dispatchedBy: 123, jobName: 'ProcessOrder');

            expect($context->userId)->toBe(123)
                ->and($context->getSource())->toBe('job')
                ->and($context->getMetadata('job_name'))->toBe('ProcessOrder');
        });
    });

    describe('forCommand', function () {
        it('creates context for artisan command', function () {
            $context = OperationContext::forCommand('app:sync-inventory');

            expect($context->userId)->toBeNull()
                ->and($context->getSource())->toBe('command')
                ->and($context->getMetadata('command_name'))->toBe('app:sync-inventory');
        });
    });

    describe('withMetadata', function () {
        it('adds metadata to existing context', function () {
            $context = OperationContext::forUser(1)
                ->withMetadata(['request_id' => 'abc-123']);

            expect($context->userId)->toBe(1)
                ->and($context->hasMetadata('request_id'))->toBeTrue()
                ->and($context->getMetadata('request_id'))->toBe('abc-123');
        });

        it('merges metadata preserving existing values', function () {
            $context = OperationContext::system()
                ->withMetadata(['extra' => 'data']);

            expect($context->getSource())->toBe('system')
                ->and($context->getMetadata('extra'))->toBe('data');
        });

        it('returns new instance (immutable)', function () {
            $original = OperationContext::forUser(1);
            $modified = $original->withMetadata(['key' => 'value']);

            expect($original)->not->toBe($modified)
                ->and($original->hasMetadata('key'))->toBeFalse()
                ->and($modified->hasMetadata('key'))->toBeTrue();
        });
    });

    describe('withUser', function () {
        it('creates new context with different user', function () {
            $original = OperationContext::forUser(1)
                ->withMetadata(['key' => 'value']);

            $modified = $original->withUser(999);

            expect($modified->userId)->toBe(999)
                ->and($modified->hasMetadata('key'))->toBeTrue()
                ->and($original->userId)->toBe(1);
        });
    });

    describe('getMetadata', function () {
        it('returns default value when key not found', function () {
            $context = OperationContext::forUser(1);

            expect($context->getMetadata('nonexistent'))->toBeNull()
                ->and($context->getMetadata('nonexistent', 'default'))->toBe('default');
        });
    });

    describe('getSource', function () {
        it('returns auth for contexts without explicit source', function () {
            $context = OperationContext::forUser(1);

            expect($context->getSource())->toBe('auth');
        });
    });
});
