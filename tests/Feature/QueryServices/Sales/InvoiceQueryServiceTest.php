<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\QueryServices\Sales\InvoiceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->logger = app(ContextualLoggerInterface::class);
    $this->queryService = new InvoiceQueryService($this->logger);
});

describe('InvoiceQueryService', function () {
    describe('findWithDetails', function () {
        it('finds invoice with all relations', function () {
            $invoice = Invoice::factory()->create();

            $result = $this->queryService->findWithDetails($invoice->id);

            expect($result)->not->toBeNull()
                ->and($result->id)->toBe($invoice->id)
                ->and($result->relationLoaded('contact'))->toBeTrue()
                ->and($result->relationLoaded('items'))->toBeTrue();
        });

        it('returns null for non-existent invoice', function () {
            $result = $this->queryService->findWithDetails(99999);

            expect($result)->toBeNull();
        });
    });

    describe('getOverdue', function () {
        it('returns overdue invoices', function () {
            Invoice::factory()->sent()->create([
                'due_date' => now()->subDays(10),
            ]);
            Invoice::factory()->sent()->create([
                'due_date' => now()->addDays(10),
            ]);

            $result = $this->queryService->getOverdue();

            expect($result)->toHaveCount(1)
                ->and($result->first()->due_date->isPast())->toBeTrue();
        });
    });

    describe('getByStatus', function () {
        it('returns invoices by status', function () {
            Invoice::factory()->draft()->count(2)->create();
            Invoice::factory()->sent()->count(3)->create();
            Invoice::factory()->paid()->count(1)->create();

            $draftInvoices = $this->queryService->getByStatus(DocumentStatus::Draft);
            $sentInvoices = $this->queryService->getByStatus(DocumentStatus::Sent);

            expect($draftInvoices)->toHaveCount(2)
                ->and($sentInvoices)->toHaveCount(3);
        });
    });

    describe('getByContact', function () {
        it('returns invoices for specific contact', function () {
            $contact = Contact::factory()->customer()->create();
            Invoice::factory()->forContact($contact)->count(3)->create();
            Invoice::factory()->count(2)->create();

            $result = $this->queryService->getByContact($contact->id);

            expect($result)->toHaveCount(3)
                ->and($result->every(fn ($inv) => $inv->contact_id === $contact->id))->toBeTrue();
        });
    });

    describe('getOutstandingForContact', function () {
        it('calculates outstanding amount for contact', function () {
            $contact = Contact::factory()->customer()->create();

            Invoice::factory()->sent()->forContact($contact)->create([
                'total_amount' => 1000000,
                'paid_amount' => 0,
            ]);
            Invoice::factory()->partial()->forContact($contact)->create([
                'total_amount' => 2000000,
                'paid_amount' => 500000,
            ]);
            Invoice::factory()->paid()->forContact($contact)->create([
                'total_amount' => 1500000,
                'paid_amount' => 1500000,
            ]);

            $outstanding = $this->queryService->getOutstandingForContact($contact->id);

            // 1,000,000 (unpaid) + 1,500,000 (partial outstanding) = 2,500,000
            expect($outstanding)->toBe(2500000);
        });
    });

    describe('getByDateRange', function () {
        it('returns invoices within date range', function () {
            Invoice::factory()->create([
                'invoice_date' => now()->subDays(5),
            ]);
            Invoice::factory()->create([
                'invoice_date' => now()->subDays(15),
            ]);
            Invoice::factory()->create([
                'invoice_date' => now()->subDays(45),
            ]);

            $range = DateRange::of(
                now()->subDays(20)->toDateString(),
                now()->toDateString()
            );
            $result = $this->queryService->getByDateRange($range);

            expect($result)->toHaveCount(2);
        });
    });

    describe('getStatistics', function () {
        it('calculates statistics for date range', function () {
            $date = now()->subDays(5);

            Invoice::factory()->draft()->create([
                'invoice_date' => $date,
                'total_amount' => 1000000,
            ]);
            Invoice::factory()->sent()->create([
                'invoice_date' => $date,
                'total_amount' => 2000000,
                'paid_amount' => 0,
            ]);
            Invoice::factory()->paid()->create([
                'invoice_date' => $date,
                'total_amount' => 3000000,
                'paid_amount' => 3000000,
            ]);

            $range = DateRange::of(
                now()->subDays(10)->toDateString(),
                now()->toDateString()
            );
            $stats = $this->queryService->getStatistics($range);

            expect($stats['counts']['total'])->toBe(3)
                ->and($stats['counts']['draft'])->toBe(1)
                ->and($stats['counts']['sent'])->toBe(1)
                ->and($stats['counts']['paid'])->toBe(1)
                ->and($stats['amounts']['total'])->toBe(6000000)
                ->and($stats['amounts']['paid'])->toBe(3000000)
                ->and($stats['amounts']['outstanding'])->toBe(3000000);
        });
    });

    describe('getAgingReport', function () {
        it('categorizes receivables by age', function () {
            // Current (not due)
            Invoice::factory()->sent()->create([
                'due_date' => now()->addDays(5),
                'total_amount' => 1000000,
                'paid_amount' => 0,
            ]);

            // 1-30 days overdue
            Invoice::factory()->sent()->create([
                'due_date' => now()->subDays(15),
                'total_amount' => 2000000,
                'paid_amount' => 0,
            ]);

            // 31-60 days overdue
            Invoice::factory()->sent()->create([
                'due_date' => now()->subDays(45),
                'total_amount' => 3000000,
                'paid_amount' => 0,
            ]);

            // Paid (should not be in aging)
            Invoice::factory()->paid()->create([
                'due_date' => now()->subDays(30),
                'total_amount' => 5000000,
                'paid_amount' => 5000000,
            ]);

            $aging = $this->queryService->getAgingReport();

            expect($aging['current']['count'])->toBe(1)
                ->and($aging['current']['amount'])->toBe(1000000)
                ->and($aging['1-30_days']['count'])->toBe(1)
                ->and($aging['1-30_days']['amount'])->toBe(2000000)
                ->and($aging['31-60_days']['count'])->toBe(1)
                ->and($aging['31-60_days']['amount'])->toBe(3000000);
        });
    });

    describe('getMonthlySummary', function () {
        it('returns monthly revenue summary', function () {
            $year = now()->year;

            Invoice::factory()->sent()->create([
                'invoice_date' => now()->startOfYear()->addMonth(),
                'total_amount' => 1000000,
            ]);
            Invoice::factory()->sent()->create([
                'invoice_date' => now()->startOfYear()->addMonth(),
                'total_amount' => 2000000,
            ]);
            Invoice::factory()->paid()->create([
                'invoice_date' => now()->startOfYear()->addMonths(2),
                'total_amount' => 3000000,
            ]);
            // Draft should not be included
            Invoice::factory()->draft()->create([
                'invoice_date' => now()->startOfYear()->addMonth(),
                'total_amount' => 5000000,
            ]);

            $summary = $this->queryService->getMonthlySummary($year);

            $month2 = $summary->firstWhere('month', 2);
            $month3 = $summary->firstWhere('month', 3);

            expect($month2['count'])->toBe(2)
                ->and($month2['total'])->toBe(3000000)
                ->and($month3['count'])->toBe(1)
                ->and($month3['total'])->toBe(3000000);
        });
    });

    describe('list', function () {
        it('lists invoices with filters', function () {
            $contact = Contact::factory()->customer()->create();
            Invoice::factory()->sent()->forContact($contact)->count(3)->create();
            Invoice::factory()->draft()->count(2)->create();

            $result = $this->queryService->list([
                'status' => DocumentStatus::Sent,
            ]);

            expect($result)->toHaveCount(3);
        });

        it('filters by search term', function () {
            Invoice::factory()->create(['invoice_number' => 'INV-2024-0001']);
            Invoice::factory()->create(['invoice_number' => 'INV-2024-0002']);

            $result = $this->queryService->list(['search' => '0001']);

            expect($result)->toHaveCount(1)
                ->and($result->first()->invoice_number)->toBe('INV-2024-0001');
        });

        it('filters by date range', function () {
            Invoice::factory()->create(['invoice_date' => now()->subDays(5)]);
            Invoice::factory()->create(['invoice_date' => now()->subDays(30)]);

            $result = $this->queryService->list([
                'date_from' => now()->subDays(10)->toDateString(),
                'date_to' => now()->toDateString(),
            ]);

            expect($result)->toHaveCount(1);
        });

        it('filters by amount range', function () {
            Invoice::factory()->create(['total_amount' => 500000]);
            Invoice::factory()->create(['total_amount' => 1500000]);
            Invoice::factory()->create(['total_amount' => 3000000]);

            $result = $this->queryService->list([
                'min_amount' => 1000000,
                'max_amount' => 2000000,
            ]);

            expect($result)->toHaveCount(1)
                ->and($result->first()->total_amount)->toBe(1500000);
        });
    });

    describe('paginate', function () {
        it('paginates invoices', function () {
            Invoice::factory()->count(25)->create();

            $result = $this->queryService->paginate([], 10);

            expect($result->count())->toBe(10)
                ->and($result->total())->toBe(25)
                ->and($result->lastPage())->toBe(3);
        });

        it('applies filters to pagination', function () {
            Invoice::factory()->sent()->count(15)->create();
            Invoice::factory()->draft()->count(10)->create();

            $result = $this->queryService->paginate(['status' => DocumentStatus::Sent], 10);

            expect($result->total())->toBe(15);
        });
    });
});
