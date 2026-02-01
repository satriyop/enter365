<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Shared\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->service = app(BillServiceInterface::class);
});

/*
|--------------------------------------------------------------------------
| BillService Create Tests
|--------------------------------------------------------------------------
*/

describe('BillService - create', function () {
    it('creates bill with basic data', function () {
        $contact = Contact::factory()->supplier()->create();

        $bill = $this->service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'Test bill',
        ]);

        expect($bill)->toBeInstanceOf(Bill::class);
        expect($bill->contact_id)->toBe($contact->id);
        expect($bill->status)->toBe(DocumentStatus::Draft);
        expect($bill->bill_number)->toStartWith('BL-');
        expect($bill->currency)->toBe('IDR');
        expect($bill->exchange_rate)->toBe('1.0000');
    });

    it('creates bill with items', function () {
        $contact = Contact::factory()->supplier()->create();

        $bill = $this->service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'Test bill',
            'items' => [
                [
                    'description' => 'Item 1',
                    'quantity' => 2,
                    'unit_price' => 500000,
                ],
                [
                    'description' => 'Item 2',
                    'quantity' => 1,
                    'unit_price' => 300000,
                ],
            ],
        ]);

        expect($bill->items)->toHaveCount(2);
        expect($bill->items->first()->description)->toBe('Item 1');
        expect((float) $bill->items->first()->quantity)->toBe(2.0);
        expect($bill->items->first()->line_total)->toBe(1000000);
        expect($bill->items->last()->line_total)->toBe(300000);
    });

    it('auto-generates unique bill number', function () {
        $contact = Contact::factory()->supplier()->create();

        $bill1 = $this->service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $bill2 = $this->service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        expect($bill1->bill_number)->not->toBe($bill2->bill_number);
    });

    it('sets default values for currency, exchange rate, and amounts', function () {
        $contact = Contact::factory()->supplier()->create();

        $bill = $this->service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        expect($bill->currency)->toBe('IDR');
        expect($bill->exchange_rate)->toBe('1.0000');
        expect($bill->subtotal)->toBe(0);
        expect($bill->discount_amount)->toBe(0);
        expect($bill->tax_amount)->toBe(0);
        expect($bill->total_amount)->toBe(0);
        expect($bill->paid_amount)->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| BillService Update Tests
|--------------------------------------------------------------------------
*/

describe('BillService - update', function () {
    it('updates draft bill', function () {
        $bill = Bill::factory()->draft()->create();

        $updated = $this->service->update($bill, [
            'description' => 'Updated description',
            'reference' => 'PO-001',
        ]);

        expect($updated->description)->toBe('Updated description');
        expect($updated->reference)->toBe('PO-001');
    });

    it('throws exception when updating non-draft bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $this->service->update($bill, ['description' => 'Cannot update']);
    })->throws(DocumentLockedException::class);

    it('updates items when updating draft bill', function () {
        $bill = Bill::factory()->draft()->create();
        BillItem::factory()->forBill($bill)->withAmount(100000)->create();

        $updated = $this->service->update($bill, [
            'items' => [
                [
                    'description' => 'New item',
                    'quantity' => 5,
                    'unit_price' => 200000,
                ],
            ],
        ]);

        expect($updated->items)->toHaveCount(1);
        expect($updated->items->first()->description)->toBe('New item');
        expect($updated->items->first()->line_total)->toBe(1000000);
    });
});

/*
|--------------------------------------------------------------------------
| BillService Delete Tests
|--------------------------------------------------------------------------
*/

describe('BillService - delete', function () {
    it('deletes draft bill', function () {
        $bill = Bill::factory()->draft()->create();
        $billId = $bill->id;

        $result = $this->service->delete($bill);

        expect($result)->toBeTrue();
        expect(Bill::find($billId))->toBeNull();
    });

    it('throws exception when deleting non-draft bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $this->service->delete($bill);
    })->throws(DocumentLockedException::class);
});

/*
|--------------------------------------------------------------------------
| BillService Post Tests
|--------------------------------------------------------------------------
*/

describe('BillService - post', function () {
    it('posts draft bill with items', function () {
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('postBill')->once();

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(BillServiceInterface::class);

        $bill = Bill::factory()
            ->has(BillItem::factory()->withAmount(500000), 'items')
            ->draft()
            ->create();

        $result = $service->post($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Received);
    });

    it('throws exception when posting already received bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $this->service->post($bill);
    })->throws(StateTransitionException::class);

    it('throws exception when posting cancelled bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->cancelled()
            ->create();

        $this->service->post($bill);
    })->throws(StateTransitionException::class);
});

/*
|--------------------------------------------------------------------------
| BillService Void Tests
|--------------------------------------------------------------------------
*/

describe('BillService - void', function () {
    it('voids received bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $result = $this->service->void($bill, 'Vendor cancelled order');

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cannot void already cancelled bill', function () {
        $bill = Bill::factory()->cancelled()->create();

        $this->service->void($bill, 'Already cancelled');
    })->throws(StateTransitionException::class);
});

/*
|--------------------------------------------------------------------------
| BillService Payment Status Tests
|--------------------------------------------------------------------------
*/

describe('BillService - markAsPaid', function () {
    it('marks received bill as paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'total_amount' => 1000000,
            ]);

        // Update paid_amount to satisfy guard
        $bill->update(['paid_amount' => 1000000]);

        $result = $this->service->markAsPaid($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('marks partial bill as paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->partial()
            ->create([
                'total_amount' => 1000000,
            ]);

        // Update paid_amount to full amount
        $bill->update(['paid_amount' => 1000000]);

        $result = $this->service->markAsPaid($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('marks overdue bill as paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->overdue()
            ->create([
                'total_amount' => 1000000,
            ]);

        // Update paid_amount to full amount
        $bill->update(['paid_amount' => 1000000]);

        $result = $this->service->markAsPaid($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('cannot mark draft bill as paid', function () {
        $bill = Bill::factory()->draft()->create();

        $this->service->markAsPaid($bill);
    })->throws(StateTransitionException::class);
});

describe('BillService - markAsPartial', function () {
    it('marks received bill as partial', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'total_amount' => 1000000,
            ]);

        // Update paid_amount to partial amount (guard requirement)
        $bill->update(['paid_amount' => 500000]);

        $result = $this->service->markAsPartial($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Partial);
    });

    it('marks overdue bill as partial', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->overdue()
            ->create([
                'total_amount' => 1000000,
            ]);

        // Update paid_amount to partial amount (guard requirement)
        $bill->update(['paid_amount' => 500000]);

        $result = $this->service->markAsPartial($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Partial);
    });

    it('cannot mark draft bill as partial', function () {
        $bill = Bill::factory()->draft()->create();

        $this->service->markAsPartial($bill);
    })->throws(StateTransitionException::class);
});

describe('BillService - markAsOverdue', function () {
    it('marks received bill as overdue', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'due_date' => now()->subDays(10),
            ]);

        $result = $this->service->markAsOverdue($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Overdue);
    });

    it('marks partial bill as overdue', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->partial()
            ->create([
                'due_date' => now()->subDays(10),
            ]);

        $result = $this->service->markAsOverdue($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Overdue);
    });

    it('cannot mark draft bill as overdue', function () {
        $bill = Bill::factory()->draft()->create();

        $this->service->markAsOverdue($bill);
    })->throws(StateTransitionException::class);

    it('cannot mark paid bill as overdue', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->paid()
            ->create();

        $this->service->markAsOverdue($bill);
    })->throws(StateTransitionException::class);
});

/*
|--------------------------------------------------------------------------
| BillService Auto-Update Payment Status Tests
|--------------------------------------------------------------------------
*/

describe('BillService - updatePaymentStatus', function () {
    it('marks bill as paid when fully paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'total_amount' => 1000000,
                'paid_amount' => 1000000,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('marks bill as paid when overpaid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'total_amount' => 1000000,
                'paid_amount' => 1200000,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('marks bill as partial when partially paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'total_amount' => 1000000,
                'paid_amount' => 500000,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Partial);
    });

    it('marks bill as overdue when past due and unpaid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'due_date' => now()->subDays(10),
                'total_amount' => 1000000,
                'paid_amount' => 0,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Overdue);
    });

    it('does not change status if already correct', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->paid()
            ->create([
                'total_amount' => 1000000,
                'paid_amount' => 1000000,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('skips cancelled bills', function () {
        $bill = Bill::factory()
            ->cancelled()
            ->create([
                'total_amount' => 1000000,
                'paid_amount' => 1000000,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('skips draft bills', function () {
        $bill = Bill::factory()
            ->draft()
            ->create([
                'total_amount' => 1000000,
                'paid_amount' => 1000000,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Draft);
    });

    it('does not mark overdue if not past due date', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create([
                'due_date' => now()->addDays(10),
                'total_amount' => 1000000,
                'paid_amount' => 0,
            ]);

        $result = $this->service->updatePaymentStatus($bill);

        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Received);
    });
});

/*
|--------------------------------------------------------------------------
| BillService Integration Tests
|--------------------------------------------------------------------------
*/

describe('BillService - integration', function () {
    it('completes full bill lifecycle', function () {
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('postBill')->once();

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(BillServiceInterface::class);
        $contact = Contact::factory()->supplier()->create();

        // 1. Create bill
        $bill = $service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'description' => 'Test item',
                    'quantity' => 1,
                    'unit_price' => 500000,
                ],
            ],
        ]);

        expect($bill->status)->toBe(DocumentStatus::Draft);
        expect($bill->items)->toHaveCount(1);

        // 2. Update while draft
        $bill = $service->update($bill, [
            'description' => 'Updated for testing',
        ]);
        expect($bill->description)->toBe('Updated for testing');

        // 3. Post bill
        $result = $service->post($bill);
        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Received);

        // 4. Mark as partial (need to refresh to get calculated total)
        $bill = $bill->fresh();
        $partialAmount = (int) ($bill->total_amount / 2);
        $bill->update(['paid_amount' => $partialAmount]);
        $result = $service->updatePaymentStatus($bill->fresh());
        expect($bill->fresh()->status)->toBe(DocumentStatus::Partial);

        // 5. Mark as fully paid
        $bill = $bill->fresh();
        $bill->update(['paid_amount' => $bill->total_amount]);
        $result = $service->updatePaymentStatus($bill->fresh());
        expect($bill->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('handles void lifecycle', function () {
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('postBill')->once();

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(BillServiceInterface::class);
        $contact = Contact::factory()->supplier()->create();

        // Create and post
        $bill = $service->create([
            'contact_id' => $contact->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'description' => 'Test item',
                    'quantity' => 1,
                    'unit_price' => 500000,
                ],
            ],
        ]);

        $service->post($bill);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Received);

        // Void
        $result = $service->void($bill->fresh(), 'Vendor cancelled order');
        expect($result)->toBeInstanceOf(Bill::class);
        expect($bill->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });
});
