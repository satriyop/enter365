---
adr: "0045"
title: "Queue Job Strategy"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [infrastructure, async]
related_adrs: []
related_modules: [core]
impact: medium
---

# ADR-0045: Queue Job Strategy

## AI Agent Quick Reference

**Use this ADR when:**
- Creating async jobs
- Implementing background processing
- Handling long-running tasks
- Understanding job priorities

**Key takeaway:** Use database queue driver with prioritized queues for different job types.

---

## Decision

Use Laravel queues with database driver and multiple priority queues.

---

## Context

Async processing needed for:
1. Email sending
2. PDF generation
3. Report generation
4. MRP calculations
5. Import/export operations

---

## Implementation

### Queue Configuration

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

### Queue Priorities

| Queue | Priority | Use Case |
|-------|----------|----------|
| high | 1 | Critical operations (payments, approvals) |
| default | 2 | Normal operations (emails, PDFs) |
| low | 3 | Bulk operations (reports, imports) |
| batch | 4 | Heavy processing (MRP, reconciliation) |

### Worker Command

```bash
# Production: process high-priority first
php artisan queue:work --queue=high,default,low,batch

# Development
php artisan queue:work --queue=default
```

### Job Structure

```php
// app/Jobs/GenerateInvoicePdf.php
class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {
        $this->onQueue('default');
    }

    public function handle(PdfService $pdfService): void
    {
        $pdf = $pdfService->generateInvoice($this->invoice);

        $this->invoice->update([
            'pdf_path' => $pdf->store('invoices'),
            'pdf_generated_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('PDF generation failed', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Dispatching Jobs

```php
// Immediate dispatch to queue
GenerateInvoicePdf::dispatch($invoice);

// Delayed dispatch
GenerateInvoicePdf::dispatch($invoice)
    ->delay(now()->addMinutes(5));

// Specific queue
GenerateInvoicePdf::dispatch($invoice)
    ->onQueue('low');

// Chained jobs
Bus::chain([
    new ProcessImport($file),
    new SendImportNotification($user),
])->dispatch();
```

### Common Jobs

| Job | Queue | Description |
|-----|-------|-------------|
| SendInvoiceEmail | default | Email invoice to customer |
| GeneratePdf | default | Create PDF document |
| RunMrpCalculation | batch | Calculate material requirements |
| ProcessImport | batch | Import data from CSV/Excel |
| GenerateReport | low | Create financial reports |
| SyncInventory | high | Update stock levels |

### Job Middleware

```php
// Rate limiting for external APIs
public function middleware(): array
{
    return [
        new RateLimited('external-api'),
    ];
}

// Skip duplicate jobs
public function middleware(): array
{
    return [
        new WithoutOverlapping($this->invoice->id),
    ];
}
```

### Failed Job Handling

```php
// config/queue.php
'failed' => [
    'driver' => 'database',
    'database' => 'pgsql',
    'table' => 'failed_jobs',
],

// Retry failed jobs
php artisan queue:retry all
php artisan queue:retry {job-id}

// Clear old failed jobs
php artisan queue:prune-failed --hours=24
```

### Monitoring

```php
// In AppServiceProvider
Queue::before(function (JobProcessing $event) {
    Log::info('Job starting', ['job' => $event->job->getName()]);
});

Queue::after(function (JobProcessed $event) {
    Log::info('Job completed', ['job' => $event->job->getName()]);
});

Queue::failing(function (JobFailed $event) {
    Log::error('Job failed', [
        'job' => $event->job->getName(),
        'error' => $event->exception->getMessage(),
    ]);
});
```

---

## References

- [System Overview](../01-architecture/system-overview.md)
- [MRP Domain](../02-domain/manufacturing.md)

