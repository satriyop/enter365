<?php

declare(strict_types=1);

use App\Contracts\Sales\QuotationServiceInterface;
use App\Domain\Sales\Quotations\QuotationDomainFactory;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);

    $this->service = app(QuotationServiceInterface::class);
});

describe('Service Resolution', function () {
    test('resolves to QuotationService implementing QuotationServiceInterface', function () {
        expect($this->service)->toBeInstanceOf(QuotationService::class);
    });

    test('is a coordinator that delegates to focused services', function () {
        // QuotationService is now a thin coordinator, not extending AbstractDocumentService
        // It delegates to QuotationCrudService, QuotationWorkflowService, etc.
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasProperty('crud'))->toBeTrue()
            ->and($reflection->hasProperty('workflow'))->toBeTrue()
            ->and($reflection->hasProperty('statistics'))->toBeTrue();
    });
});

describe('CRUD Operations', function () {
    test('creates quotation with items', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'Test Quotation via New Service',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Test Item',
                    'quantity' => 2,
                    'unit_price' => 500000,
                ],
            ],
        ];

        $quotation = $this->service->create($data, $this->user);

        expect($quotation)
            ->toBeInstanceOf(Quotation::class)
            ->status->toBe(DocumentStatus::Draft)
            ->subject->toBe('Test Quotation via New Service')
            ->contact_id->toBe($this->contact->id)
            ->items->toHaveCount(1);

        expect($quotation->items->first())
            ->product_id->toBe($this->product->id)
            ->description->toBe('Test Item');

        // Quantity may be stored as decimal string, check equality loosely
        expect((float) $quotation->items->first()->quantity)->toBe(2.0);
        expect((int) $quotation->items->first()->unit_price)->toBe(500000);
    });

    test('generates quotation number', function () {
        $quotation = $this->service->create([
            'contact_id' => $this->contact->id,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ], $this->user);

        expect($quotation->quotation_number)
            ->not->toBeEmpty()
            ->toContain('QUO-');
    });

    test('calculates totals correctly', function () {
        $quotation = $this->service->create([
            'contact_id' => $this->contact->id,
            'tax_rate' => 11.00,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 100000], // 200000
                ['description' => 'Item 2', 'quantity' => 1, 'unit_price' => 300000], // 300000
            ],
        ], $this->user);

        expect($quotation)
            ->subtotal->toBe(500000)
            ->tax_amount->toBe(55000) // 500000 * 0.11
            ->total_amount->toBe(555000);    // 500000 + 55000
    });

    test('updates draft quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'subject' => 'Original Subject',
            ]);

        $updated = $this->service->update($quotation, [
            'subject' => 'Updated Subject',
        ]);

        expect($updated->subject)->toBe('Updated Subject');
    });

    test('throws exception when updating non-draft quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]);

        $this->service->update($quotation, ['subject' => 'New Subject']);
    })->throws(DocumentLockedException::class, 'Hanya penawaran draft');
});

describe('State Transitions', function () {
    test('submits draft quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create(['contact_id' => $this->contact->id]);

        $submitted = $this->service->submit($quotation, $this->user->id);

        expect($submitted)
            ->status->toBe(DocumentStatus::Submitted)
            ->submitted_at->not->toBeNull();
    });

    test('approves submitted quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]);

        $approved = $this->service->approve($quotation, $this->user->id);

        expect($approved)
            ->status->toBe(DocumentStatus::Approved)
            ->approved_at->not->toBeNull();
    });

    test('rejects submitted quotation with reason', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]);

        $rejected = $this->service->reject($quotation, 'Harga terlalu tinggi', $this->user->id);

        expect($rejected)
            ->status->toBe(DocumentStatus::Rejected)
            ->rejection_reason->toBe('Harga terlalu tinggi');
    });

    test('cancels quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'next_follow_up_at' => now()->addDays(3),
            ]);

        $cancelled = $this->service->cancel($quotation, 'Customer tidak jadi order', $this->user->id);

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled)
            ->next_follow_up_at->toBeNull();
    });
});

describe('Revision and Duplication', function () {
    test('revises approved quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'status' => DocumentStatus::Approved,
                'approved_at' => now(),
            ]);

        $this->actingAs($this->user);
        $revision = $this->service->revise($quotation);

        expect($revision)
            ->status->toBe(DocumentStatus::Draft)
            ->original_quotation_id->toBe($quotation->id)
            ->revision->toBeGreaterThan(0)
            ->items->toHaveCount($quotation->items->count());
    });

    test('duplicates quotation as new draft', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory()->count(2), 'items')
            ->create(['contact_id' => $this->contact->id]);

        $this->actingAs($this->user);
        $duplicate = $this->service->duplicate($quotation);

        expect($duplicate)
            ->status->toBe(DocumentStatus::Draft)
            ->original_quotation_id->toBeNull()
            ->quotation_number->not->toBe($quotation->quotation_number)
            ->items->toHaveCount(2);
    });
});

describe('Mark as Sent', function () {
    test('marks approved quotation as sent', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'status' => DocumentStatus::Approved,
                'approved_at' => now(),
            ]);

        $this->actingAs($this->user);
        $sent = $this->service->markAsSent($quotation, 'customer@example.com', 'email');

        expect($sent)
            ->sent_at->not->toBeNull()
            ->sent_to_email->toBe('customer@example.com')
            ->sent_via->toBe('email')
            ->isSent()->toBeTrue();
    });

    test('throws exception when marking non-approved quotation as sent', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'status' => DocumentStatus::Draft,
            ]);

        $this->service->markAsSent($quotation);
    })->throws(StateTransitionException::class, 'approved');
});

describe('Expiration', function () {
    test('marks expired quotations', function () {
        // Create expired quotations
        Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'valid_until' => now()->subDay(),
            ]);

        Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now()->subDays(5),
                'valid_until' => now()->subDay(),
            ]);

        // Create non-expired quotation
        Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Draft,
                'valid_until' => now()->addWeek(),
            ]);

        $count = $this->service->markExpired();

        expect($count)->toBe(2);
        expect(Quotation::where('status', DocumentStatus::Expired)->count())->toBe(2);
    });
});

describe('BOM Integration', function () {
    test('creates quotation from active BOM', function () {
        $product = Product::factory()->create(['purchase_price' => 1000000]);
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create([
                'product_id' => $product->id,
                'status' => DocumentStatus::Active,
                'total_cost' => 500000,
            ]);

        $this->actingAs($this->user);
        $quotation = $this->service->createFromBom([
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'margin_percent' => 20,
        ]);

        expect($quotation)
            ->toBeInstanceOf(Quotation::class)
            ->status->toBe(DocumentStatus::Draft)
            ->source_bom_id->toBe($bom->id)
            ->items->toHaveCount(1); // Single line item for BOM product
    });

    test('throws exception for inactive BOM', function () {
        $bom = Bom::factory()->create(['status' => DocumentStatus::Draft]);

        $this->service->createFromBom([
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
        ]);
    })->throws(BusinessRuleException::class, 'aktif');
});

describe('Statistics', function () {
    test('returns quotation statistics', function () {
        Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->count(3)
            ->create(['status' => DocumentStatus::Approved]);

        Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Approved,
                'outcome' => 'won',
            ]);

        $stats = $this->service->getStatistics();

        expect($stats)->toBeArray()
            ->and($stats)->toHaveKey('total');
    });
});

describe('Operation Context Integration', function () {
    test('uses getUserId from OperationContext when set', function () {
        $context = \App\Support\OperationContext::forUser($this->user->id);
        $serviceWithContext = $this->service->withContext($context);

        $quotation = $serviceWithContext->create([
            'contact_id' => $this->contact->id,
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        expect($quotation->created_by)->toBe($this->user->id);
    });
});

describe('Domain Factory Usage', function () {
    test('uses QuotationDomainFactory for state machine', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->create(['contact_id' => $this->contact->id]);

        $domainFactory = app(QuotationDomainFactory::class);
        $stateMachine = $domainFactory->stateMachine($quotation);

        expect($stateMachine->canSubmit())->toBeTrue();
        expect($stateMachine->canApprove())->toBeFalse();
    });

    test('uses QuotationDomainFactory for total calculation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory()->count(2), 'items')
            ->create([
                'contact_id' => $this->contact->id,
                'subtotal' => 0,
                'total_amount' => 0,
            ]);

        $domainFactory = app(QuotationDomainFactory::class);
        $domainFactory->applyTotals($quotation);
        $quotation->save();

        $quotation->refresh();
        expect($quotation->subtotal)->toBeGreaterThan(0);
    });
});
