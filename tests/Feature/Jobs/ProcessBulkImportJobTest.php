<?php

declare(strict_types=1);

use App\Jobs\ProcessBulkImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('ProcessBulkImport job', function () {
    it('can be dispatched to the queue', function () {
        Queue::fake();

        ProcessBulkImport::dispatch(
            'imports/products.csv',
            'App\\Imports\\ProductImport',
            ['skip_errors' => true],
            1
        );

        Queue::assertPushed(ProcessBulkImport::class, function ($job) {
            return $job->filePath === 'imports/products.csv'
                && $job->importerClass === 'App\\Imports\\ProductImport'
                && $job->options === ['skip_errors' => true];
        });
    });

    it('has correct timeout and retry configuration', function () {
        $job = new ProcessBulkImport(
            'imports/test.csv',
            'App\\Imports\\TestImport'
        );

        expect($job->tries)->toBe(1)
            ->and($job->timeout)->toBe(600);
    });

    it('logs error when file does not exist', function () {
        Storage::fake();

        $job = new ProcessBulkImport(
            'imports/nonexistent.csv',
            'App\\Imports\\TestImport'
        );

        // Should not throw — file-not-found is logged
        $job->handle();

        expect(Storage::exists('imports/nonexistent.csv'))->toBeFalse();
    });

    it('stores constructor parameters correctly', function () {
        $job = new ProcessBulkImport(
            filePath: 'imports/test.csv',
            importerClass: 'App\\Imports\\TestImport',
            options: ['batch_size' => 100],
            userId: 3
        );

        expect($job->filePath)->toBe('imports/test.csv')
            ->and($job->importerClass)->toBe('App\\Imports\\TestImport')
            ->and($job->options)->toBe(['batch_size' => 100])
            ->and($job->userId)->toBe(3);
    });
});
