<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Process a bulk import file in the background.
 *
 * Handles CSV/Excel imports that may contain thousands of rows,
 * preventing HTTP timeouts during large file processing.
 */
class ProcessBulkImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  string  $filePath  Path to uploaded file in storage
     * @param  string  $importerClass  FQCN of the importer to use
     * @param  array<string, mixed>  $options  Additional import options
     * @param  int|null  $userId  User who initiated the import
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $importerClass,
        public readonly array $options = [],
        public readonly ?int $userId = null
    ) {}

    public function handle(): void
    {
        Log::info("ProcessBulkImport: Starting import from {$this->filePath}", [
            'importer' => $this->importerClass,
            'options' => $this->options,
        ]);

        if (! Storage::exists($this->filePath)) {
            Log::error("ProcessBulkImport: File not found at {$this->filePath}");

            return;
        }

        $importer = app($this->importerClass);
        $fullPath = Storage::path($this->filePath);

        $importer->import($fullPath);

        Log::info("ProcessBulkImport: Import completed for {$this->filePath}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessBulkImport: Failed for {$this->filePath}", [
            'importer' => $this->importerClass,
            'error' => $exception->getMessage(),
        ]);
    }
}
