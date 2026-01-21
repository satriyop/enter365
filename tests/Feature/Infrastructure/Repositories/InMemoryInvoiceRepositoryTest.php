<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Infrastructure\Repositories\InMemory\InMemoryInvoiceRepository;
use App\Models\Sales\Invoice;
use Carbon\Carbon;

beforeEach(function () {
    $this->repository = new InMemoryInvoiceRepository;
});

test('create adds invoice to repository', function () {
    $invoice = $this->repository->create([
        'invoice_number' => 'INV-001',
        'status' => DocumentStatus::Draft,
        'total_amount' => 100000,
        'paid_amount' => 0,
    ]);

    expect($invoice->id)->toBe(1)
        ->and($invoice->invoice_number)->toBe('INV-001');
});

test('find returns invoice by id', function () {
    $created = $this->repository->create([
        'invoice_number' => 'INV-001',
        'status' => DocumentStatus::Draft,
    ]);

    $found = $this->repository->find($created->id);

    expect($found)->not->toBeNull()
        ->and($found->invoice_number)->toBe('INV-001');
});

test('find returns null for non-existent id', function () {
    $found = $this->repository->find(999);

    expect($found)->toBeNull();
});

test('findOrFail throws exception for non-existent id', function () {
    $this->repository->findOrFail(999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('all returns all invoices', function () {
    $this->repository->create(['invoice_number' => 'INV-001', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-002', 'status' => DocumentStatus::Confirmed]);
    $this->repository->create(['invoice_number' => 'INV-003', 'status' => DocumentStatus::Paid]);

    $all = $this->repository->all();

    expect($all)->toHaveCount(3);
});

test('findBy returns matching invoices', function () {
    $this->repository->create(['invoice_number' => 'INV-001', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-002', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-003', 'status' => DocumentStatus::Confirmed]);

    $drafts = $this->repository->findBy(['status' => DocumentStatus::Draft]);

    expect($drafts)->toHaveCount(2);
});

test('update modifies invoice', function () {
    $invoice = $this->repository->create([
        'invoice_number' => 'INV-001',
        'status' => DocumentStatus::Draft,
    ]);

    $updated = $this->repository->update($invoice->id, [
        'status' => DocumentStatus::Confirmed,
    ]);

    expect($updated->status)->toBe(DocumentStatus::Confirmed);
});

test('delete removes invoice', function () {
    $invoice = $this->repository->create(['invoice_number' => 'INV-001']);

    $result = $this->repository->delete($invoice->id);

    expect($result)->toBeTrue()
        ->and($this->repository->find($invoice->id))->toBeNull();
});

test('delete returns false for non-existent id', function () {
    $result = $this->repository->delete(999);

    expect($result)->toBeFalse();
});

test('count returns number of invoices', function () {
    $this->repository->create(['invoice_number' => 'INV-001']);
    $this->repository->create(['invoice_number' => 'INV-002']);

    expect($this->repository->count())->toBe(2);
});

test('count with criteria returns filtered count', function () {
    $this->repository->create(['invoice_number' => 'INV-001', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-002', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-003', 'status' => DocumentStatus::Confirmed]);

    expect($this->repository->count(['status' => DocumentStatus::Draft]))->toBe(2);
});

test('exists returns true for matching criteria', function () {
    $this->repository->create(['invoice_number' => 'INV-001']);

    expect($this->repository->exists(['invoice_number' => 'INV-001']))->toBeTrue();
});

test('exists returns false for non-matching criteria', function () {
    expect($this->repository->exists(['invoice_number' => 'INV-NONEXISTENT']))->toBeFalse();
});

test('clear removes all invoices', function () {
    $this->repository->create(['invoice_number' => 'INV-001']);
    $this->repository->create(['invoice_number' => 'INV-002']);

    $this->repository->clear();

    expect($this->repository->count())->toBe(0);
});

test('add allows adding existing entity', function () {
    $invoice = new Invoice;
    $invoice->id = 100;
    $invoice->invoice_number = 'INV-EXISTING';
    $invoice->status = DocumentStatus::Draft;

    $this->repository->add($invoice);

    $found = $this->repository->find(100);
    expect($found)->not->toBeNull()
        ->and($found->invoice_number)->toBe('INV-EXISTING');
});

test('findByStatus returns invoices with given status', function () {
    $this->repository->create(['invoice_number' => 'INV-001', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-002', 'status' => DocumentStatus::Draft]);
    $this->repository->create(['invoice_number' => 'INV-003', 'status' => DocumentStatus::Confirmed]);

    $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

    expect($drafts)->toHaveCount(2);
});

test('findOverdue returns overdue invoices', function () {
    // Overdue invoice
    $overdue = new Invoice;
    $overdue->id = 1;
    $overdue->invoice_number = 'INV-001';
    $overdue->due_date = Carbon::now()->subDays(5);
    $overdue->status = DocumentStatus::Confirmed;
    $this->repository->add($overdue);

    // Paid invoice (not overdue)
    $paid = new Invoice;
    $paid->id = 2;
    $paid->invoice_number = 'INV-002';
    $paid->due_date = Carbon::now()->subDays(5);
    $paid->status = DocumentStatus::Paid;
    $this->repository->add($paid);

    // Future invoice (not overdue)
    $future = new Invoice;
    $future->id = 3;
    $future->invoice_number = 'INV-003';
    $future->due_date = Carbon::now()->addDays(5);
    $future->status = DocumentStatus::Confirmed;
    $this->repository->add($future);

    $overdueInvoices = $this->repository->findOverdue();

    expect($overdueInvoices)->toHaveCount(1)
        ->and($overdueInvoices->first()->invoice_number)->toBe('INV-001');
});

test('findByContact returns invoices for contact', function () {
    $this->repository->create([
        'invoice_number' => 'INV-001',
        'contact_id' => 1,
    ]);
    $this->repository->create([
        'invoice_number' => 'INV-002',
        'contact_id' => 1,
    ]);
    $this->repository->create([
        'invoice_number' => 'INV-003',
        'contact_id' => 2,
    ]);

    $invoices = $this->repository->findByContact(1);

    expect($invoices)->toHaveCount(2);
});

test('findByDateRange returns invoices within range', function () {
    $inRange = new Invoice;
    $inRange->id = 1;
    $inRange->invoice_number = 'INV-001';
    $inRange->invoice_date = Carbon::now()->subDays(5);
    $this->repository->add($inRange);

    $beforeRange = new Invoice;
    $beforeRange->id = 2;
    $beforeRange->invoice_number = 'INV-002';
    $beforeRange->invoice_date = Carbon::now()->subDays(20);
    $this->repository->add($beforeRange);

    $afterRange = new Invoice;
    $afterRange->id = 3;
    $afterRange->invoice_number = 'INV-003';
    $afterRange->invoice_date = Carbon::now()->addDays(5);
    $this->repository->add($afterRange);

    $range = DateRange::of(Carbon::now()->subDays(10)->toDateString(), Carbon::now()->toDateString());
    $invoices = $this->repository->findByDateRange($range);

    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->invoice_number)->toBe('INV-001');
});

test('getOutstandingForContact calculates outstanding amount', function () {
    // Confirmed invoice with partial payment
    $invoice1 = new Invoice;
    $invoice1->id = 1;
    $invoice1->contact_id = 1;
    $invoice1->total_amount = 100000;
    $invoice1->paid_amount = 30000;
    $invoice1->status = DocumentStatus::Confirmed;
    $this->repository->add($invoice1);

    // Another confirmed invoice
    $invoice2 = new Invoice;
    $invoice2->id = 2;
    $invoice2->contact_id = 1;
    $invoice2->total_amount = 50000;
    $invoice2->paid_amount = 0;
    $invoice2->status = DocumentStatus::Confirmed;
    $this->repository->add($invoice2);

    // Paid invoice (should not count)
    $invoice3 = new Invoice;
    $invoice3->id = 3;
    $invoice3->contact_id = 1;
    $invoice3->total_amount = 200000;
    $invoice3->paid_amount = 200000;
    $invoice3->status = DocumentStatus::Paid;
    $this->repository->add($invoice3);

    $outstanding = $this->repository->getOutstandingForContact(1);

    // (100000 - 30000) + (50000 - 0) = 120000
    expect($outstanding)->toBe(120000);
});

test('findByNumber returns invoice by number', function () {
    $this->repository->create([
        'invoice_number' => 'INV-FIND-001',
        'status' => DocumentStatus::Draft,
    ]);

    $found = $this->repository->findByNumber('INV-FIND-001');

    expect($found)->not->toBeNull()
        ->and($found->invoice_number)->toBe('INV-FIND-001');
});
