<?php

declare(strict_types=1);

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Infrastructure\Repositories\Sales\EloquentInvoiceRepository;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(InvoiceRepositoryInterface::class);
});

test('repository is bound to eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentInvoiceRepository::class);
});

test('find returns invoice by id', function () {
    $invoice = Invoice::factory()->create();

    $found = $this->repository->find($invoice->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($invoice->id);
});

test('find returns null for non-existent id', function () {
    $found = $this->repository->find(999999);

    expect($found)->toBeNull();
});

test('findOrFail throws exception for non-existent id', function () {
    $this->repository->findOrFail(999999);
})->throws(EntityNotFoundException::class);

test('findByStatus returns invoices with given status', function () {
    Invoice::factory()->count(3)->create(['status' => DocumentStatus::Draft]);
    Invoice::factory()->count(2)->create(['status' => DocumentStatus::Confirmed]);

    $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

    expect($drafts)->toHaveCount(3)
        ->each(fn ($invoice) => $invoice->status->toBe(DocumentStatus::Draft));
});

test('findOverdue returns only overdue invoices', function () {
    // Create overdue invoice (past due date, not paid/cancelled/draft)
    Invoice::factory()->create([
        'due_date' => Carbon::now()->subDays(5),
        'status' => DocumentStatus::Confirmed,
    ]);

    // Create paid invoice (should not be included)
    Invoice::factory()->create([
        'due_date' => Carbon::now()->subDays(5),
        'status' => DocumentStatus::Paid,
    ]);

    // Create future invoice (should not be included)
    Invoice::factory()->create([
        'due_date' => Carbon::now()->addDays(5),
        'status' => DocumentStatus::Confirmed,
    ]);

    $overdue = $this->repository->findOverdue();

    expect($overdue)->toHaveCount(1);
});

test('findByContact returns invoices for contact', function () {
    $contact = Contact::factory()->create();
    Invoice::factory()->count(3)->create(['contact_id' => $contact->id]);
    Invoice::factory()->count(2)->create(); // Different contacts

    $invoices = $this->repository->findByContact($contact->id);

    expect($invoices)->toHaveCount(3)
        ->each(fn ($invoice) => $invoice->contact_id->toBe($contact->id));
});

test('findByDateRange returns invoices within range', function () {
    $startDate = Carbon::now()->subDays(10);
    $endDate = Carbon::now();

    // Invoice within range
    Invoice::factory()->create([
        'invoice_date' => Carbon::now()->subDays(5),
    ]);

    // Invoice before range
    Invoice::factory()->create([
        'invoice_date' => Carbon::now()->subDays(20),
    ]);

    // Invoice after range
    Invoice::factory()->create([
        'invoice_date' => Carbon::now()->addDays(5),
    ]);

    $range = DateRange::of($startDate->toDateString(), $endDate->toDateString());
    $invoices = $this->repository->findByDateRange($range);

    expect($invoices)->toHaveCount(1);
});

test('getOutstandingForContact returns correct outstanding amount', function () {
    $contact = Contact::factory()->create();

    // Confirmed invoice with partial payment
    Invoice::factory()->create([
        'contact_id' => $contact->id,
        'total_amount' => 100000,
        'paid_amount' => 30000,
        'status' => DocumentStatus::Confirmed,
    ]);

    // Another confirmed invoice
    Invoice::factory()->create([
        'contact_id' => $contact->id,
        'total_amount' => 50000,
        'paid_amount' => 0,
        'status' => DocumentStatus::Confirmed,
    ]);

    // Paid invoice (should not be counted)
    Invoice::factory()->create([
        'contact_id' => $contact->id,
        'total_amount' => 200000,
        'paid_amount' => 200000,
        'status' => DocumentStatus::Paid,
    ]);

    $outstanding = $this->repository->getOutstandingForContact($contact->id);

    // (100000 - 30000) + (50000 - 0) = 120000
    expect($outstanding)->toBe(120000);
});

test('findByNumber returns invoice by invoice number', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'INV-2024-001',
    ]);

    $found = $this->repository->findByNumber('INV-2024-001');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($invoice->id);
});

test('findWithRelations loads all relations', function () {
    $invoice = Invoice::factory()->create();

    $found = $this->repository->findWithRelations($invoice->id);

    expect($found)->not->toBeNull()
        ->and($found->relationLoaded('contact'))->toBeTrue()
        ->and($found->relationLoaded('items'))->toBeTrue();
});

test('create persists new invoice', function () {
    $contact = Contact::factory()->create();

    $invoice = $this->repository->create([
        'contact_id' => $contact->id,
        'invoice_number' => 'INV-TEST-001',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => DocumentStatus::Draft,
        'subtotal' => 100000,
        'tax_amount' => 10000,
        'total_amount' => 110000,
        'paid_amount' => 0,
    ]);

    expect($invoice->id)->not->toBeNull()
        ->and($invoice->invoice_number)->toBe('INV-TEST-001');

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => 'INV-TEST-001',
    ]);
});

test('update modifies invoice', function () {
    $invoice = Invoice::factory()->create([
        'status' => DocumentStatus::Draft,
    ]);

    $updated = $this->repository->update($invoice->id, [
        'status' => DocumentStatus::Confirmed,
    ]);

    expect($updated->status)->toBe(DocumentStatus::Confirmed);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => DocumentStatus::Confirmed->value,
    ]);
});

test('delete soft deletes invoice', function () {
    $invoice = Invoice::factory()->create();
    $id = $invoice->id;

    $result = $this->repository->delete($id);

    expect($result)->toBeTrue();
    $this->assertSoftDeleted('invoices', ['id' => $id]);
});

test('count returns total number of invoices', function () {
    Invoice::factory()->count(5)->create();

    $count = $this->repository->count();

    expect($count)->toBe(5);
});

test('exists returns true when matching record exists', function () {
    Invoice::factory()->create([
        'invoice_number' => 'INV-EXISTS-001',
    ]);

    $exists = $this->repository->exists(['invoice_number' => 'INV-EXISTS-001']);

    expect($exists)->toBeTrue();
});

test('exists returns false when no matching record', function () {
    $exists = $this->repository->exists(['invoice_number' => 'INV-NONEXISTENT']);

    expect($exists)->toBeFalse();
});
