---
adr: "0046"
title: "File Storage Strategy"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [infrastructure, storage]
related_adrs: []
related_modules: [core]
impact: medium
---

# ADR-0046: File Storage Strategy

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing file uploads
- Working with document attachments
- Managing generated PDFs
- Understanding storage organization

**Key takeaway:** Use Laravel Storage with local disk for development, S3-compatible for production.

---

## Decision

Use Laravel Storage facade with organized directory structure and S3-compatible storage for production.

---

## Context

File storage needed for:
1. Document attachments
2. Generated PDFs
3. Import/export files
4. Profile photos

---

## Implementation

### Storage Configuration

```php
// config/filesystems.php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],

    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],

    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
    ],
],

'default' => env('FILESYSTEM_DISK', 'local'),
```

### Directory Structure

```
storage/app/
├── public/                      # Publicly accessible
│   ├── avatars/                # User profile photos
│   └── logos/                  # Company logos
│
├── private/                    # Protected files
│   ├── invoices/              # Generated invoice PDFs
│   ├── quotations/            # Generated quotation PDFs
│   ├── reports/               # Generated reports
│   ├── attachments/           # Document attachments
│   │   ├── invoices/
│   │   ├── bills/
│   │   └── projects/
│   └── imports/               # Temporary import files
│
└── temp/                       # Temporary processing
```

### File Model

```php
// attachments table
$table->morphs('attachable');           // Parent model
$table->string('filename');              // Original name
$table->string('path');                  // Storage path
$table->string('disk');                  // Storage disk
$table->string('mime_type');
$table->bigInteger('size');
$table->foreignId('uploaded_by');
$table->timestamps();
```

### Attachment Trait

```php
// app/Traits/HasAttachments.php
trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attach(UploadedFile $file): Attachment
    {
        $path = $file->store(
            $this->getAttachmentPath(),
            config('filesystems.default')
        );

        return $this->attachments()->create([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => config('filesystems.default'),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);
    }

    protected function getAttachmentPath(): string
    {
        $type = Str::plural(Str::snake(class_basename($this)));
        return "private/attachments/{$type}/{$this->id}";
    }
}
```

### File Upload Handling

```php
public function uploadAttachment(Request $request, Invoice $invoice)
{
    $request->validate([
        'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,png,doc,docx'],
    ]);

    $attachment = $invoice->attach($request->file('file'));

    return response()->json([
        'attachment' => $attachment,
    ]);
}
```

### Secure File Download

```php
public function download(Attachment $attachment)
{
    $this->authorize('view', $attachment->attachable);

    return Storage::disk($attachment->disk)
        ->download($attachment->path, $attachment->filename);
}
```

### PDF Generation Storage

```php
public function generateInvoicePdf(Invoice $invoice): string
{
    $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));

    $path = "private/invoices/{$invoice->number}.pdf";

    Storage::put($path, $pdf->output());

    $invoice->update(['pdf_path' => $path]);

    return $path;
}
```

### Temporary Files

```php
// Store temporarily (auto-cleanup)
$path = $file->store('temp');

// Process
$this->processImport($path);

// Cleanup
Storage::delete($path);
```

### Storage Limits

| File Type | Max Size | Allowed Formats |
|-----------|----------|-----------------|
| Attachments | 10 MB | pdf, jpg, png, doc, docx |
| Imports | 50 MB | csv, xlsx |
| Photos | 2 MB | jpg, png |

---

## References

- [System Overview](../01-architecture/system-overview.md)

