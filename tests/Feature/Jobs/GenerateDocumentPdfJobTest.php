<?php

declare(strict_types=1);

use App\Jobs\GenerateDocumentPdf;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('GenerateDocumentPdf job', function () {
    it('can be dispatched to the queue', function () {
        Queue::fake();

        $invoice = Invoice::factory()->create();

        GenerateDocumentPdf::dispatch(
            $invoice,
            'pdfs.invoice',
            'documents/invoices/INV-001.pdf',
            1
        );

        Queue::assertPushed(GenerateDocumentPdf::class, function ($job) use ($invoice) {
            return $job->document->id === $invoice->id
                && $job->template === 'pdfs.invoice'
                && $job->storagePath === 'documents/invoices/INV-001.pdf';
        });
    });

    it('has correct retry configuration', function () {
        $invoice = Invoice::factory()->create();
        $job = new GenerateDocumentPdf($invoice, 'pdfs.invoice', 'test.pdf');

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe(30);
    });

    it('stores constructor parameters correctly', function () {
        $invoice = Invoice::factory()->create();

        $job = new GenerateDocumentPdf(
            document: $invoice,
            template: 'pdfs.invoice',
            storagePath: 'documents/test.pdf',
            userId: 5
        );

        expect($job->document->id)->toBe($invoice->id)
            ->and($job->template)->toBe('pdfs.invoice')
            ->and($job->storagePath)->toBe('documents/test.pdf')
            ->and($job->userId)->toBe(5);
    });
});
