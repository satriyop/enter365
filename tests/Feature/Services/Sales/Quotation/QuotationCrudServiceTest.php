<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\Sales\QuotationVariantOption;
use App\Models\User;
use App\Services\Sales\Quotation\QuotationCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create();
    $this->product = Product::factory()->create();
    $this->service = app(QuotationCrudService::class);

    actingAs($this->user);
});

describe('create', function () {
    it('creates quotation with items and generates number', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'Test Quotation',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Product Item',
                    'quantity' => 2,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                ],
            ],
            'tax_rate' => 11,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ];

        $quotation = $this->service->create($data);

        expect($quotation)
            ->toBeInstanceOf(Quotation::class)
            ->quotation_number->not->toBeNull()
            ->contact_id->toBe($this->contact->id)
            ->subject->toBe('Test Quotation')
            ->status->toBe(DocumentStatus::Draft)
            ->created_by->toBe($this->user->id);

        expect($quotation->items)->toHaveCount(1);
        expect($quotation->items[0])
            ->product_id->toBe($this->product->id)
            ->quantity->toBe('2.0000')
            ->unit_price->toBe(100000);

        expect($quotation->total_amount)->toBeGreaterThan(0);
    });

    it('creates quotation with authenticated user context', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'Test Quotation',
            'items' => [],
        ];

        $quotation = $this->service->create($data, $this->user);

        expect($quotation->created_by)->toBe($this->user->id);
    });

    it('creates quotation with user ID parameter', function () {
        $anotherUser = User::factory()->create();

        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'Test Quotation',
            'items' => [],
        ];

        $quotation = $this->service->create($data, $anotherUser->id);

        expect($quotation->created_by)->toBe($anotherUser->id);
    });

    it('returns quotation with items and contact loaded', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'Test Quotation',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Product Item',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                ],
            ],
        ];

        $quotation = $this->service->create($data);

        expect($quotation->relationLoaded('items'))->toBeTrue();
        expect($quotation->relationLoaded('contact'))->toBeTrue();
    });

    it('calculates totals correctly', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'Test Quotation',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Item 1',
                    'quantity' => 2,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                ],
                [
                    'product_id' => $this->product->id,
                    'description' => 'Item 2',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 50000,
                ],
            ],
            'tax_rate' => 0,
            'discount_type' => 'none',
        ];

        $quotation = $this->service->create($data);

        // 2 * 100000 + 1 * 50000 = 250000
        expect($quotation->subtotal)->toBe(250000);
        expect($quotation->total_amount)->toBe(250000);
    });
});

describe('update', function () {
    it('updates draft quotation successfully', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
            'subject' => 'Original Subject',
        ]);

        $data = [
            'subject' => 'Updated Subject',
            'notes' => 'Updated notes',
        ];

        $updated = $this->service->update($quotation, $data);

        expect($updated)
            ->subject->toBe('Updated Subject')
            ->notes->toBe('Updated notes');
    });

    it('updates items when provided', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        QuotationItem::factory()->for($quotation)->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
        ]);

        $data = [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'New Item',
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 200000,
                ],
            ],
        ];

        $updated = $this->service->update($quotation, $data);

        expect($updated->items)->toHaveCount(1);
        expect($updated->items[0])
            ->quantity->toBe('5.0000')
            ->unit_price->toBe(200000);
    });

    it('deletes existing items and creates new ones', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $oldItem1 = QuotationItem::factory()->for($quotation)->create();
        $oldItem2 = QuotationItem::factory()->for($quotation)->create();

        $data = [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'New Item',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                ],
            ],
        ];

        $updated = $this->service->update($quotation, $data);

        expect(QuotationItem::find($oldItem1->id))->toBeNull();
        expect(QuotationItem::find($oldItem2->id))->toBeNull();
        expect($updated->items)->toHaveCount(1);
    });

    it('recalculates totals after update', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
            'total_amount' => 0,
        ]);

        $data = [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Item',
                    'quantity' => 2,
                    'unit' => 'pcs',
                    'unit_price' => 150000,
                ],
            ],
            'tax_rate' => 0,
            'discount_type' => 'none',
        ];

        $updated = $this->service->update($quotation, $data);

        expect($updated->total_amount)->toBe(300000);
    });

    it('throws exception for submitted quotation', function () {
        $quotation = Quotation::factory()->submitted()->create([
            'contact_id' => $this->contact->id,
        ]);

        $data = ['subject' => 'Updated'];

        $this->service->update($quotation, $data);
    })->throws(DocumentLockedException::class);

    it('throws exception for approved quotation', function () {
        $quotation = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
        ]);

        $data = ['subject' => 'Updated'];

        $this->service->update($quotation, $data);
    })->throws(DocumentLockedException::class);

    it('throws exception for rejected quotation', function () {
        $quotation = Quotation::factory()->rejected()->create([
            'contact_id' => $this->contact->id,
        ]);

        $data = ['subject' => 'Updated'];

        $this->service->update($quotation, $data);
    })->throws(DocumentLockedException::class);

    it('throws exception for expired quotation', function () {
        $quotation = Quotation::factory()->expired()->create([
            'contact_id' => $this->contact->id,
        ]);

        $data = ['subject' => 'Updated'];

        $this->service->update($quotation, $data);
    })->throws(DocumentLockedException::class);
});

describe('delete', function () {
    it('deletes draft quotation successfully', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $quotationId = $quotation->id;

        $result = $this->service->delete($quotation);

        expect($result)->toBeTrue();
        expect(Quotation::find($quotationId))->toBeNull();
    });

    it('deletes items before deleting quotation', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $item = QuotationItem::factory()->for($quotation)->create();
        $itemId = $item->id;

        $this->service->delete($quotation);

        expect(QuotationItem::find($itemId))->toBeNull();
    });

    it('throws exception for submitted quotation', function () {
        $quotation = Quotation::factory()->submitted()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->delete($quotation);
    })->throws(DocumentLockedException::class);

    it('throws exception for approved quotation', function () {
        $quotation = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->delete($quotation);
    })->throws(DocumentLockedException::class);

    it('throws exception for rejected quotation', function () {
        $quotation = Quotation::factory()->rejected()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->delete($quotation);
    })->throws(DocumentLockedException::class);

    it('throws exception for expired quotation', function () {
        $quotation = Quotation::factory()->expired()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->delete($quotation);
    })->throws(DocumentLockedException::class);
});

describe('createFromBom', function () {
    it('creates quotation from active BOM with default margin', function () {
        $bom = Bom::factory()->active()->create([
            'product_id' => $this->product->id,
            'total_cost' => 1000000,
        ]);

        BomItem::factory()->for($bom)->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 1000000,
        ]);

        $data = [
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
        ];

        $quotation = $this->service->createFromBom($data);

        expect($quotation)
            ->toBeInstanceOf(Quotation::class)
            ->contact_id->toBe($this->contact->id)
            ->subject->toBe('BOM Quotation');

        // Default margin is 20%, so selling price should be 1000000 * 1.20 = 1200000
        expect($quotation->total_amount)->toBeGreaterThan(1000000);
    });

    it('creates quotation with custom margin percent', function () {
        $bom = Bom::factory()->active()->create([
            'product_id' => $this->product->id,
            'total_cost' => 1000000,
        ]);

        BomItem::factory()->for($bom)->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 1000000,
        ]);

        $data = [
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
            'margin_percent' => 30,
        ];

        $quotation = $this->service->createFromBom($data);

        // 30% margin: 1000000 * 1.30 = 1300000
        expect($quotation->total_amount)->toBeGreaterThanOrEqual(1300000);
    });

    it('creates quotation with custom selling price', function () {
        $bom = Bom::factory()->active()->create([
            'product_id' => $this->product->id,
            'total_cost' => 1000000,
        ]);

        BomItem::factory()->for($bom)->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 1000000,
        ]);

        $data = [
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
            'selling_price' => 1500000,
        ];

        $quotation = $this->service->createFromBom($data);

        expect($quotation->total_amount)->toBeGreaterThanOrEqual(1500000);
    });

    it('creates single item when expand_items is false', function () {
        $bom = Bom::factory()->active()->create([
            'product_id' => $this->product->id,
            'total_cost' => 1000000,
        ]);

        BomItem::factory()->for($bom)->count(3)->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 333333,
        ]);

        $data = [
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
            'expand_items' => false,
        ];

        $quotation = $this->service->createFromBom($data);

        expect($quotation->items)->toHaveCount(1);
    });

    it('expands items when expand_items is true', function () {
        $bom = Bom::factory()->active()->create([
            'product_id' => $this->product->id,
            'total_cost' => 1000000,
        ]);

        BomItem::factory()->for($bom)->count(3)->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 333333,
        ]);

        $data = [
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
            'expand_items' => true,
        ];

        $quotation = $this->service->createFromBom($data);

        expect($quotation->items)->toHaveCount(3);
    });

    it('throws exception when bom_id is missing', function () {
        $data = [
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
        ];

        $this->service->createFromBom($data);
    })->throws(BusinessRuleException::class, 'BOM');

    it('throws exception when BOM does not exist', function () {
        $data = [
            'bom_id' => 99999,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
        ];

        $this->service->createFromBom($data);
    })->throws(EntityNotFoundException::class);

    it('throws exception when BOM is not active', function () {
        $bom = Bom::factory()->create([
            'product_id' => $this->product->id,
            'status' => DocumentStatus::Draft,
        ]);

        $data = [
            'bom_id' => $bom->id,
            'contact_id' => $this->contact->id,
            'subject' => 'BOM Quotation',
        ];

        $this->service->createFromBom($data);
    })->throws(BusinessRuleException::class, 'aktif');
});

describe('duplicate', function () {
    it('creates new draft from any quotation', function () {
        $original = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
            'subject' => 'Original Subject',
        ]);

        QuotationItem::factory()->for($original)->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100000,
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate)
            ->id->not->toBe($original->id)
            ->quotation_number->not->toBe($original->quotation_number)
            ->status->toBe(DocumentStatus::Draft)
            ->contact_id->toBe($original->contact_id)
            ->subject->toBe($original->subject)
            ->created_by->toBe($this->user->id);

        expect($duplicate->items)->toHaveCount(1);
    });

    it('generates new quotation number for duplicate', function () {
        $original = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate->quotation_number)
            ->not->toBeNull()
            ->not->toBe($original->quotation_number);
    });

    it('copies items to duplicate', function () {
        $original = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
        ]);

        $item1 = QuotationItem::factory()->for($original)->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100000,
        ]);

        $item2 = QuotationItem::factory()->for($original)->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 150000,
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate->items)->toHaveCount(2);
        expect($duplicate->items[0]->product_id)->toBe($item1->product_id);
        expect($duplicate->items[1]->product_id)->toBe($item2->product_id);
    });

    it('can duplicate from draft quotation', function () {
        $original = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate->status)->toBe(DocumentStatus::Draft);
    });

    it('can duplicate from submitted quotation', function () {
        $original = Quotation::factory()->submitted()->create([
            'contact_id' => $this->contact->id,
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate->status)->toBe(DocumentStatus::Draft);
    });

    it('can duplicate from rejected quotation', function () {
        $original = Quotation::factory()->rejected()->create([
            'contact_id' => $this->contact->id,
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate->status)->toBe(DocumentStatus::Draft);
    });
});

describe('revise', function () {
    it('creates revision from approved quotation', function () {
        $original = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
            'subject' => 'Original Subject',
        ]);

        QuotationItem::factory()->for($original)->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100000,
        ]);

        $revision = $this->service->revise($original);

        expect($revision)
            ->id->not->toBe($original->id)
            ->quotation_number->toBe($original->quotation_number) // Revisions keep same number
            ->revision->toBe(1) // But increment revision
            ->status->toBe(DocumentStatus::Draft)
            ->original_quotation_id->toBe($original->id)
            ->created_by->toBe($this->user->id);

        expect($revision->items)->toHaveCount(1);
    });

    it('creates revision from rejected quotation', function () {
        $original = Quotation::factory()->rejected()->create([
            'contact_id' => $this->contact->id,
        ]);

        $revision = $this->service->revise($original);

        expect($revision)
            ->status->toBe(DocumentStatus::Draft)
            ->original_quotation_id->toBe($original->id);
    });

    it('creates revision from expired quotation', function () {
        $original = Quotation::factory()->expired()->create([
            'contact_id' => $this->contact->id,
        ]);

        $revision = $this->service->revise($original);

        expect($revision)
            ->status->toBe(DocumentStatus::Draft)
            ->original_quotation_id->toBe($original->id);
    });

    it('increments revision number correctly', function () {
        $original = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
            'revision' => 0,
        ]);

        $revision = $this->service->revise($original);

        expect($revision->revision)->toBe(1);
    });

    it('links to original quotation when revising a revision', function () {
        $original = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
        ]);

        $firstRevision = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
            'original_quotation_id' => $original->id,
            'revision' => 1,
        ]);

        $secondRevision = $this->service->revise($firstRevision);

        expect($secondRevision->original_quotation_id)->toBe($original->id);
    });

    it('throws exception for draft quotation', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->revise($quotation);
    })->throws(StateTransitionException::class);

    it('throws exception for submitted quotation', function () {
        $quotation = Quotation::factory()->submitted()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->revise($quotation);
    })->throws(StateTransitionException::class);
});

describe('syncVariantOptions', function () {
    it('creates variant options for draft quotation', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $bom1 = Bom::factory()->active()->create(['product_id' => $this->product->id]);
        $bom2 = Bom::factory()->active()->create(['product_id' => $this->product->id]);

        $options = [
            [
                'bom_id' => $bom1->id,
                'display_name' => 'Basic Package',
                'tagline' => 'Essential features',
                'is_recommended' => false,
                'selling_price' => 1000000,
                'features' => ['Feature 1', 'Feature 2'],
            ],
            [
                'bom_id' => $bom2->id,
                'display_name' => 'Premium Package',
                'tagline' => 'All features included',
                'is_recommended' => true,
                'selling_price' => 2000000,
                'features' => ['Feature 1', 'Feature 2', 'Feature 3'],
            ],
        ];

        $result = $this->service->syncVariantOptions($quotation, $options);

        expect($result)->toHaveCount(2);
        expect($result[0])
            ->display_name->toBe('Basic Package')
            ->selling_price->toBe(1000000)
            ->is_recommended->toBeFalse();

        expect($result[1])
            ->display_name->toBe('Premium Package')
            ->is_recommended->toBeTrue();
    });

    it('updates quotation type to multi-option', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
            'quotation_type' => QuotationType::Single->value,
        ]);

        $bom = Bom::factory()->active()->create(['product_id' => $this->product->id]);

        $options = [
            [
                'bom_id' => $bom->id,
                'display_name' => 'Package',
                'selling_price' => 1000000,
            ],
        ];

        $this->service->syncVariantOptions($quotation, $options);

        $quotation->refresh();
        expect($quotation->quotation_type)->toBe(QuotationType::MultiOption);
    });

    it('deletes existing variant options before creating new ones', function () {
        $quotation = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $oldOption = QuotationVariantOption::factory()->forQuotation($quotation)->create();
        $oldOptionId = $oldOption->id;

        $bom = Bom::factory()->active()->create(['product_id' => $this->product->id]);

        $options = [
            [
                'bom_id' => $bom->id,
                'display_name' => 'New Package',
                'selling_price' => 1000000,
            ],
        ];

        $this->service->syncVariantOptions($quotation, $options);

        expect(QuotationVariantOption::find($oldOptionId))->toBeNull();
    });

    it('sets sort_order based on array index', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $bom1 = Bom::factory()->active()->create(['product_id' => $this->product->id]);
        $bom2 = Bom::factory()->active()->create(['product_id' => $this->product->id]);

        $options = [
            [
                'bom_id' => $bom1->id,
                'display_name' => 'First',
                'selling_price' => 1000000,
            ],
            [
                'bom_id' => $bom2->id,
                'display_name' => 'Second',
                'selling_price' => 2000000,
            ],
        ];

        $result = $this->service->syncVariantOptions($quotation, $options);

        expect($result[0]->sort_order)->toBe(0);
        expect($result[1]->sort_order)->toBe(1);
    });

    it('loads BOM relationship in results', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
        ]);

        $bom = Bom::factory()->active()->create(['product_id' => $this->product->id]);

        $options = [
            [
                'bom_id' => $bom->id,
                'display_name' => 'Package',
                'selling_price' => 1000000,
            ],
        ];

        $result = $this->service->syncVariantOptions($quotation, $options);

        expect($result[0]->relationLoaded('bom'))->toBeTrue();
        expect($result[0]->bom->id)->toBe($bom->id);
    });

    it('throws exception for non-draft quotation', function () {
        $quotation = Quotation::factory()->approved()->create([
            'contact_id' => $this->contact->id,
        ]);

        $bom = Bom::factory()->active()->create(['product_id' => $this->product->id]);

        $options = [
            [
                'bom_id' => $bom->id,
                'display_name' => 'Package',
                'selling_price' => 1000000,
            ],
        ];

        $this->service->syncVariantOptions($quotation, $options);
    })->throws(DocumentLockedException::class);
});

describe('selectVariant', function () {
    it('selects variant for multi-option quotation', function () {
        $quotation = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $variantOption = QuotationVariantOption::factory()->forQuotation($quotation)->create([
            'selling_price' => 1500000,
        ]);

        $result = $this->service->selectVariant($quotation, $variantOption->id);

        expect($result)
            ->selected_variant_id->toBe($variantOption->bom_id)
            ->total_amount->toBe(1500000);
    });

    it('updates total_amount to variant selling_price', function () {
        $quotation = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
            'total_amount' => 0,
        ]);

        $variantOption = QuotationVariantOption::factory()->forQuotation($quotation)->create([
            'selling_price' => 2500000,
        ]);

        $result = $this->service->selectVariant($quotation, $variantOption->id);

        expect($result->total_amount)->toBe(2500000);
    });

    it('throws exception for non-multi-option quotation', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
            'quotation_type' => QuotationType::Single->value,
        ]);

        $variantOption = QuotationVariantOption::factory()->forQuotation($quotation)->create();

        $this->service->selectVariant($quotation, $variantOption->id);
    })->throws(BusinessRuleException::class, 'multi-option');

    it('throws exception for non-existent variant', function () {
        $quotation = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->selectVariant($quotation, 99999);
    })->throws(EntityNotFoundException::class);

    it('throws exception for variant from different quotation', function () {
        $quotation1 = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $quotation2 = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $variantOption = QuotationVariantOption::factory()->forQuotation($quotation2)->create();

        $this->service->selectVariant($quotation1, $variantOption->id);
    })->throws(BusinessRuleException::class, 'tidak valid');
});

describe('clearVariantSelection', function () {
    it('clears selected variant for multi-option quotation', function () {
        $quotation = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $variantOption = QuotationVariantOption::factory()->forQuotation($quotation)->create();

        $quotation->update(['selected_variant_id' => $variantOption->bom_id]);

        $result = $this->service->clearVariantSelection($quotation);

        expect($result->selected_variant_id)->toBeNull();
    });

    it('throws exception for non-multi-option quotation', function () {
        $quotation = Quotation::factory()->draft()->create([
            'contact_id' => $this->contact->id,
            'quotation_type' => QuotationType::Single->value,
        ]);

        $this->service->clearVariantSelection($quotation);
    })->throws(BusinessRuleException::class, 'multi-option');

    it('returns quotation with loaded relations', function () {
        $quotation = Quotation::factory()->draft()->multiOption()->create([
            'contact_id' => $this->contact->id,
        ]);

        $result = $this->service->clearVariantSelection($quotation);

        expect($result->relationLoaded('items'))->toBeTrue();
        expect($result->relationLoaded('contact'))->toBeTrue();
    });
});
