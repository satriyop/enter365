<?php

declare(strict_types=1);

use App\Infrastructure\Logging\LaravelContextualLogger;
use Illuminate\Support\Facades\Log;

describe('LaravelContextualLogger', function () {

    it('logs service entry with sanitized parameters', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Service method entry'
                    && $context['parameters']['password'] === '[REDACTED]'
                    && $context['parameters']['email'] === 'test@example.com'
                    && $context['class'] === 'TestService'
                    && $context['method'] === 'login';
            });

        $logger = new LaravelContextualLogger;
        $logger->logEntry('TestService', 'login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
    });

    it('sanitizes multiple sensitive keys', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['parameters']['token'] === '[REDACTED]'
                    && $context['parameters']['api_key'] === '[REDACTED]'
                    && $context['parameters']['secret'] === '[REDACTED]'
                    && $context['parameters']['normal_field'] === 'value';
            });

        $logger = new LaravelContextualLogger;
        $logger->logEntry('TestService', 'process', [
            'token' => 'abc123',
            'api_key' => 'key456',
            'secret' => 'mysecret',
            'normal_field' => 'value',
        ]);
    });

    it('logs service exit with result type', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Service method exit'
                    && $context['result_type'] === 'array'
                    && $context['class'] === 'TestService'
                    && $context['method'] === 'getData';
            });

        $logger = new LaravelContextualLogger;
        $logger->logExit('TestService', 'getData', ['data' => 'test']);
    });

    it('logs null result type correctly', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['result_type'] === 'null';
            });

        $logger = new LaravelContextualLogger;
        $logger->logExit('TestService', 'doSomething', null);
    });

    it('logs domain events with extracted data', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Domain event fired'
                    && str_contains($context['event_class'], 'class@anonymous')
                    && isset($context['event_data']);
            });

        $event = new class
        {
            public int $invoiceId = 123;

            public string $status = 'sent';
        };

        $logger = new LaravelContextualLogger;
        $logger->logDomainEvent($event);
    });

    it('logs operations with context', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Operation: invoice.created'
                    && $context['invoice_id'] === 456
                    && $context['amount'] === 1000000;
            });

        $logger = new LaravelContextualLogger;
        $logger->logOperation('invoice.created', [
            'invoice_id' => 456,
            'amount' => 1000000,
        ]);
    });

    it('logs errors with full context', function () {
        $exception = new \RuntimeException('Something went wrong');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Something went wrong'
                    && $context['exception_class'] === 'RuntimeException'
                    && isset($context['file'])
                    && isset($context['line'])
                    && isset($context['trace'])
                    && $context['additional_info'] === 'extra data';
            });

        $logger = new LaravelContextualLogger;
        $logger->logError($exception, ['additional_info' => 'extra data']);
    });

    it('logs performance metrics with duration', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_starts_with($message, 'Performance: ')
                    && $context['duration_ms'] === 150.5
                    && $context['metrics']['rows_processed'] === 100;
            });

        $logger = new LaravelContextualLogger;
        $logger->logPerformance('database.query', 0.1505, ['rows_processed' => 100]);
    });

    it('includes global context in all log calls', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return isset($context['app_version'])
                    && isset($context['environment']);
            });

        $logger = new LaravelContextualLogger;
        $logger->logEntry('TestService', 'method');
    });

    it('handles object parameters by logging class name and id', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                // Object should be converted to "ClassName:id" format
                return str_contains($context['parameters']['user'], 'stdClass:123');
            });

        $user = new \stdClass;
        $user->id = 123;

        $logger = new LaravelContextualLogger;
        $logger->logEntry('TestService', 'process', ['user' => $user]);
    });
});
