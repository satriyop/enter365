<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Generate an Excel export in the background.
 *
 * Wraps maatwebsite/excel exports to prevent HTTP timeouts
 * when exporting large datasets.
 */
class GenerateExcelExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  string  $exportClass  FQCN of the export class
     * @param  string  $storagePath  Storage path for the generated file
     * @param  array<string, mixed>  $constructorArgs  Arguments for the export class constructor
     * @param  int|null  $userId  User who initiated the export
     */
    public function __construct(
        public readonly string $exportClass,
        public readonly string $storagePath,
        public readonly array $constructorArgs = [],
        public readonly ?int $userId = null
    ) {}

    public function handle(): void
    {
        Log::info("GenerateExcelExport: Generating export to {$this->storagePath}", [
            'export_class' => $this->exportClass,
        ]);

        $export = app()->make($this->exportClass, $this->constructorArgs);

        Excel::store($export, $this->storagePath);

        Log::info("GenerateExcelExport: Export saved to {$this->storagePath}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateExcelExport: Failed for {$this->storagePath}", [
            'export_class' => $this->exportClass,
            'error' => $exception->getMessage(),
        ]);
    }
}
