<?php

declare(strict_types=1);

/**
 * Regression coverage for audit P0 hotfixes (F-01 subset, F-02).
 */

use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Contracts\Shared\ReminderServiceInterface;
use App\Exceptions\Domain\DocumentLockedException;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\Invoice;
use App\Models\Shared\Attachment;
use App\Models\Shared\PaymentReminder;
use App\Models\User;
use App\Services\Shared\AttachmentService;
use App\Support\OperationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('F-02 OperationContext user id (no auth()->id() in services)', function () {
    it('sets attachment uploaded_by from OperationContext without relying on request user', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create();
        $file = UploadedFile::fake()->create('ctx.pdf', 100, 'application/pdf');

        $service = app(AttachmentService::class)
            ->withContext(OperationContext::forUser($user->id));

        $attachment = $service->create($file, [
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
        ]);

        expect($attachment->uploaded_by)->toBe($user->id)
            ->and(Attachment::query()->whereKey($attachment->id)->value('uploaded_by'))->toBe($user->id);
    });
});

describe('F-01 PO delete via service', function () {
    it('deletes draft purchase orders through PurchaseOrderService', function () {
        $po = PurchaseOrder::factory()->draft()->create();

        $deleted = app(PurchaseOrderServiceInterface::class)->delete($po);

        expect($deleted)->toBeTrue();
        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    });

    it('refuses to delete non-draft purchase orders via service', function () {
        $po = PurchaseOrder::factory()->approved()->create();

        expect(fn () => app(PurchaseOrderServiceInterface::class)->delete($po))
            ->toThrow(DocumentLockedException::class);
    });

    it('API destroy routes through service (draft soft-deletes)', function () {
        authenticatedAdmin();
        $po = PurchaseOrder::factory()->draft()->create();

        $this->deleteJson("/api/v1/purchase-orders/{$po->id}")
            ->assertOk();

        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    });
});

describe('F-01 PaymentReminder create via ReminderService', function () {
    it('schedules manual invoice reminder with created_by from OperationContext', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $reminder = app(ReminderServiceInterface::class)
            ->withContext(OperationContext::forUser($user->id))
            ->scheduleManualInvoiceReminder($invoice, [
                'scheduled_date' => now()->addDays(5)->toDateString(),
                'type' => PaymentReminder::TYPE_UPCOMING,
                'channel' => PaymentReminder::CHANNEL_EMAIL,
                'message' => 'Audit regression reminder',
            ]);

        expect($reminder)->toBeInstanceOf(PaymentReminder::class)
            ->and($reminder->remindable_id)->toBe($invoice->id)
            ->and($reminder->created_by)->toBe($user->id)
            ->and($reminder->status)->toBe(PaymentReminder::STATUS_PENDING);
    });

    it('controller store does not call PaymentReminder::create directly', function () {
        $src = file_get_contents(app_path('Http/Controllers/Api/V1/PaymentReminderController.php')) ?: '';

        expect($src)->toContain('scheduleManualInvoiceReminder')
            ->and($src)->toContain('createAndSendImmediateInvoiceReminder')
            ->and($src)->not->toContain('PaymentReminder::create');
    });
});
