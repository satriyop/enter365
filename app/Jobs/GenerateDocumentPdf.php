<?php

declare(strict_types=1);

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generate PDF for a document (Invoice, Bill, Quotation, etc.).
 *
 * Offloads dompdf rendering to the queue to prevent HTTP timeouts
 * on large/complex documents.
 */
class GenerateDocumentPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly Model $document,
        public readonly string $template,
        public readonly string $storagePath,
        public readonly ?int $userId = null
    ) {}

    public function handle(): void
    {
        $documentClass = class_basename($this->document);
        $documentId = $this->document->getKey();

        Log::info("GenerateDocumentPdf: Generating PDF for {$documentClass} #{$documentId}");

        $pdf = Pdf::loadView($this->template, [
            'document' => $this->document->load($this->getEagerLoadRelations()),
        ]);

        $pdf->setPaper('a4', 'portrait');

        Storage::put($this->storagePath, $pdf->output());

        Log::info("GenerateDocumentPdf: PDF saved to {$this->storagePath}");
    }

    public function failed(\Throwable $exception): void
    {
        $documentClass = class_basename($this->document);
        $documentId = $this->document->getKey();

        Log::error("GenerateDocumentPdf: Failed for {$documentClass} #{$documentId}", [
            'error' => $exception->getMessage(),
            'storage_path' => $this->storagePath,
        ]);
    }

    /**
     * @return list<string>
     */
    private function getEagerLoadRelations(): array
    {
        return match (class_basename($this->document)) {
            'Invoice' => ['items', 'contact', 'payments'],
            'Bill' => ['items', 'contact', 'payments'],
            'Quotation' => ['items', 'contact'],
            default => ['contact'],
        };
    }
}
