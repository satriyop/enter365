<?php

declare(strict_types=1);

use App\Jobs\GenerateExcelExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('GenerateExcelExport job', function () {
    it('can be dispatched to the queue', function () {
        Queue::fake();

        GenerateExcelExport::dispatch(
            'App\\Exports\\SolarProposalExport',
            'exports/proposal.xlsx',
            ['proposal_id' => 1],
            1
        );

        Queue::assertPushed(GenerateExcelExport::class, function ($job) {
            return $job->exportClass === 'App\\Exports\\SolarProposalExport'
                && $job->storagePath === 'exports/proposal.xlsx'
                && $job->constructorArgs === ['proposal_id' => 1];
        });
    });

    it('has correct retry configuration', function () {
        $job = new GenerateExcelExport(
            'App\\Exports\\SolarProposalExport',
            'exports/test.xlsx'
        );

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe(30);
    });

    it('stores constructor parameters correctly', function () {
        $job = new GenerateExcelExport(
            exportClass: 'App\\Exports\\SolarProposalExport',
            storagePath: 'exports/test.xlsx',
            constructorArgs: ['id' => 42],
            userId: 5
        );

        expect($job->exportClass)->toBe('App\\Exports\\SolarProposalExport')
            ->and($job->storagePath)->toBe('exports/test.xlsx')
            ->and($job->constructorArgs)->toBe(['id' => 42])
            ->and($job->userId)->toBe(5);
    });
});
