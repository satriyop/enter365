<?php

declare(strict_types=1);

use App\Models\Sales\Invoice;
use App\Models\Shared\Attachment;
use App\Models\User;
use App\Services\Shared\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(AttachmentService::class);
});

describe('create', function () {
    it('creates attachment from uploaded file', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        expect($attachment)
            ->toBeInstanceOf(Attachment::class)
            ->filename->toBe('document.pdf')
            ->mime_type->toBe('application/pdf')
            ->attachable_type->toBe(Invoice::class)
            ->attachable_id->toBe($invoice->id)
            ->uploaded_by->toBe($this->user->id)
            ->category->toBe(Attachment::CATEGORY_OTHER);
    });

    it('stores file to disk', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        Storage::disk('local')->assertExists($attachment->path);
    });

    it('creates attachment with description and category', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
            'description' => 'Kwitansi pembayaran',
            'category' => Attachment::CATEGORY_RECEIPT,
        ]);

        expect($attachment->description)->toBe('Kwitansi pembayaran')
            ->and($attachment->category)->toBe(Attachment::CATEGORY_RECEIPT);
    });

    it('stores file size correctly', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('large.pdf', 2048, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        expect($attachment->size)->toBeGreaterThan(0);
    });

    it('generates unique path under attachments folder', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        expect($attachment->path)->toStartWith('attachments/');
    });

    it('loads uploader relationship on return', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        expect($attachment->relationLoaded('uploader'))->toBeTrue()
            ->and($attachment->uploader->id)->toBe($this->user->id);
    });
});

describe('delete', function () {
    it('deletes attachment record from database', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('to-delete.pdf', 100, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        $this->service->delete($attachment);

        expect(Attachment::find($attachment->id))->toBeNull();
    });

    it('deletes file from storage', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('to-delete.pdf', 100, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        $path = $attachment->path;
        Storage::disk('local')->assertExists($path);

        $this->service->delete($attachment);

        Storage::disk('local')->assertMissing($path);
    });

    it('handles deletion when file already missing from storage', function () {
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('ghost.pdf', 100, 'application/pdf');

        $attachment = $this->service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        // Manually remove the file first
        Storage::disk('local')->delete($attachment->path);

        // Should not throw
        $this->service->delete($attachment);

        expect(Attachment::find($attachment->id))->toBeNull();
    });
});
