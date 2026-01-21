# Factory States Registry

Quick reference for all factory states and relationship methods in Enter365.

---

## Sales Factories

### InvoiceFactory

```php
Invoice::factory()
    // Status states
    ->draft()                    // Default draft status
    ->sent()                     // Posted to customer
    ->partial()                  // Partial payment received (50%)
    ->paid()                     // Fully paid
    ->overdue()                  // Past due date
    ->cancelled()                // Cancelled

    // Financial modifiers
    ->withoutTax()               // No tax
    ->withDiscount(50000)        // Fixed discount amount

    // Relationships
    ->forContact($contact)       // Link to customer
    ->withReceivableAccount($account)
    ->createdBy($user)
    ->create();
```

### QuotationFactory

```php
Quotation::factory()
    // Status states
    ->draft()                    // Default
    ->submitted()                // Sent for approval
    ->approved()                 // Approved
    ->rejected()                 // Rejected with reason
    ->expired()                  // Past validity date
    ->converted()                // Converted to Invoice

    // Outcome states
    ->won('harga_kompetitif')    // Won with reason
    ->lost('harga_tinggi', 'Competitor ABC')

    // Type modifiers
    ->multiOption()              // Multi-option quotation
    ->singleOption()             // Single option

    // Priority
    ->highPriority()
    ->urgentPriority()

    // Financial modifiers
    ->withPercentageDiscount(10) // 10% discount
    ->withFixedDiscount(100000)  // Fixed discount
    ->withoutTax()
    ->validFor(30)               // Valid for 30 days

    // Follow-up
    ->withFollowUp(3)            // Follow-up in 3 days
    ->overdueFollowUp()          // Overdue follow-up

    // Relationships
    ->forContact($contact)
    ->createdBy($user)
    ->assignedTo($user)
    ->revision($originalQuotation)
    ->create();
```

### DeliveryOrderFactory

```php
DeliveryOrder::factory()
    // Status states
    ->draft()
    ->confirmed()
    ->shipped()                  // With shipping dates
    ->delivered()                // All delivery info populated
    ->cancelled()

    // Modifiers
    ->withShippingInfo()         // Address, method, driver, vehicle

    // Relationships
    ->forInvoice($invoice)
    ->forContact($contact)
    ->fromWarehouse($warehouse)
    ->create();
```

### DownPaymentFactory

```php
DownPayment::factory()
    // Type states
    ->receivable()               // From customer
    ->payable()                  // To vendor

    // Application states
    ->partiallyApplied(50000)    // Partially applied
    ->fullyApplied()             // Fully applied
    ->refunded()                 // Refunded
    ->cancelled()

    // Modifiers
    ->withAmount(1000000)        // Specific amount

    // Relationships
    ->forContact($contact)
    ->create();
```

### SalesReturnFactory

```php
SalesReturn::factory()
    // Status states
    ->draft()
    ->submitted()
    ->approved()
    ->completed()
    ->cancelled()

    // Modifiers
    ->withTotals(100000)         // Sets subtotal, tax, total

    // Relationships
    ->forInvoice($invoice)
    ->forContact($contact)
    ->atWarehouse($warehouse)
    ->create();
```

### InvoiceItemFactory

```php
InvoiceItem::factory()
    ->forProduct($product)       // Sets description, price from product
    ->forInvoice($invoice)
    ->withRevenueAccount($account)
    ->withAmount(10000, 2)       // Unit price, quantity
    ->create();
```

---

## Purchasing Factories

### BillFactory

```php
Bill::factory()
    // Status states
    ->draft()
    ->received()                 // Posted
    ->partial()                  // Partial payment (50%)
    ->paid()                     // Fully paid
    ->overdue()
    ->cancelled()

    // Modifiers
    ->withoutTax()

    // Relationships
    ->forContact($vendor)
    ->withPayableAccount($account)
    ->createdBy($user)
    ->create();
```

### PurchaseOrderFactory

```php
PurchaseOrder::factory()
    // Status states
    ->draft()
    ->submitted()                // With submission info
    ->approved()
    ->rejected()                 // With rejection reason
    ->partial()                  // Partially received
    ->received()                 // Fully received
    ->cancelled()
    ->converted()                // Linked to Bill

    // Financial modifiers
    ->withPercentageDiscount(10)
    ->withFixedDiscount(100000)
    ->withoutTax()
    ->expectedIn(14)             // Expected in 14 days

    // Relationships
    ->forContact($vendor)
    ->createdBy($user)
    ->create();
```

### GoodsReceiptNoteFactory

```php
GoodsReceiptNote::factory()
    // Status states
    ->draft()
    ->receiving()                // In progress
    ->completed()
    ->cancelled()

    // Relationships
    ->forPurchaseOrder($po)
    ->forWarehouse($warehouse)
    ->create();
```

### PurchaseReturnFactory

```php
PurchaseReturn::factory()
    // Status states
    ->draft()
    ->submitted()
    ->approved()
    ->completed()
    ->cancelled()

    // Modifiers
    ->withTotals(100000)

    // Relationships
    ->forBill($bill)
    ->forContact($vendor)
    ->atWarehouse($warehouse)
    ->create();
```

---

## Manufacturing Factories

### BomFactory

```php
Bom::factory()
    // Status states
    ->draft()
    ->active()
    ->inactive()

    // Cost modifiers
    ->withTotals(500000, 200000, 100000) // Material, labor, overhead
    ->withOutputQuantity(10)

    // Variant support
    ->forVariantGroup($group, 'Option A')
    ->asPrimaryVariant()
    ->withVariantSortOrder(1)

    // Relationships
    ->forProduct($product)
    ->create();
```

### WorkOrderFactory

```php
WorkOrder::factory()
    // Type states
    ->production()               // Production work order
    ->installation()             // Installation work order

    // Status states
    ->draft()
    ->confirmed()
    ->inProgress()               // With start dates
    ->completed()                // With completion info
    ->cancelled()

    // Priority
    ->highPriority()
    ->urgent()

    // Cost modifiers
    ->withEstimatedCosts(1000000, 500000, 200000) // Material, labor, overhead
    ->withActualCosts(1100000, 550000, 220000)

    // Relationships
    ->forProject($project)
    ->withBom($bom)
    ->forProduct($product)
    ->withWarehouse($warehouse)
    ->subWorkOrder($parentWorkOrder)
    ->create();
```

### MrpRunFactory

```php
MrpRun::factory()
    // Status states
    ->draft()
    ->processing()
    ->completed()                // With analysis results
    ->applied()                  // With application timestamp

    // Modifiers
    ->withHorizon($start, $end)  // Planning horizon
    ->withParameters([...])

    // Relationships
    ->withWarehouse($warehouse)
    ->withCreator($user)
    ->create();
```

---

## Accounting Factories

### JournalEntryFactory

```php
JournalEntry::factory()
    // Status states
    ->posted()
    ->reversed()
    ->manual()                   // Manual entry (no source)

    // Relationships
    ->forFiscalPeriod($period)
    ->createdBy($user)
    ->create();
```

### FiscalPeriodFactory

```php
FiscalPeriod::factory()
    // Status states
    ->current()                  // Current fiscal year
    ->closed()                   // Closed and locked
    ->closing()                  // In closing process
    ->locked()                   // Permanently locked
    ->create();
```

---

## Inventory Factories

### ProductFactory

```php
Product::factory()
    // Type states
    ->service()                  // Service product (not inventoried)

    // Status states
    ->inactive()

    // Stock states
    ->lowStock()
    ->outOfStock()

    // Modifiers
    ->withCategory($category)
    ->notSellable()
    ->notPurchasable()
    ->taxFree()
    ->withPrices(10000, 8000)    // Sell price, cost price
    ->create();
```

### WarehouseFactory

```php
Warehouse::factory()
    ->default()                  // Default warehouse
    ->inactive()
    ->create();
```

---

## Contacts Factory

### ContactFactory

```php
Contact::factory()
    // Type states
    ->customer()                 // Customer only
    ->supplier()                 // Supplier/vendor only
    ->vendor()                   // Alias for supplier
    ->both()                     // Both customer and supplier

    // Status
    ->inactive()

    // Modifiers
    ->withNpwp()                 // Indonesian tax ID
    ->individual()               // Individual (not company)
    ->subcontractor()            // With service rates
    ->withSubcontractorRates(100000, 800000) // Hourly, daily
    ->create();
```

---

## Projects Factory

### ProjectFactory

```php
Project::factory()
    // Status states
    ->draft()
    ->planning()
    ->inProgress()               // With progress percentage
    ->onHold()
    ->completed()                // 100% progress, end dates
    ->cancelled()
    ->overdue()                  // Past deadline

    // Priority
    ->highPriority()
    ->urgent()

    // Financial
    ->withFinancials(10000000, 15000000) // Cost, revenue

    // Relationships
    ->forContact($contact)
    ->fromQuotation($quotation)
    ->create();
```

---

## Common Patterns

### Chaining Multiple States

```php
// Invoice with items, paid, for specific customer
$invoice = Invoice::factory()
    ->paid()
    ->forContact($customer)
    ->has(InvoiceItem::factory()->count(3))
    ->create();

// Quotation with follow-up, high priority, for customer
$quotation = Quotation::factory()
    ->submitted()
    ->highPriority()
    ->withFollowUp(3)
    ->forContact($customer)
    ->create();

// Work order with BOM and warehouse
$workOrder = WorkOrder::factory()
    ->production()
    ->inProgress()
    ->withBom($bom)
    ->withWarehouse($warehouse)
    ->forProject($project)
    ->create();
```

### Creating with Related Items

**⚠️ GOTCHA: Always use `->has()` for relationships, not custom methods**

Don't assume factories have custom `withItems()` methods. Use Laravel's standard `->has()`:

```php
// ❌ WRONG - withItems() may not exist on all factories
$quotation = Quotation::factory()->withItems(3)->create();
// Error: Call to undefined method QuotationFactory::withItems()

// ✅ CORRECT - Standard Laravel pattern
$quotation = Quotation::factory()
    ->has(QuotationItem::factory()->count(3), 'items')
    ->create();
```

**Standard pattern for all document factories:**

```php
// Invoice with 3 items
Invoice::factory()
    ->has(InvoiceItem::factory()->count(3), 'items')
    ->create();

// Quotation with items
Quotation::factory()
    ->has(QuotationItem::factory()->count(2), 'items')
    ->create();

// BOM with items
Bom::factory()
    ->has(BomItem::factory()->count(5), 'items')
    ->create();

// Work Order with items
WorkOrder::factory()
    ->has(WorkOrderItem::factory()->count(3), 'items')
    ->create();
```

**When creating for state transition tests, always include items:**

```php
// State transitions often require items to exist
$quotation = Quotation::factory()
    ->has(QuotationItem::factory(), 'items')  // At least 1 item
    ->create(['contact_id' => $contact->id]);

$submitted = $service->submit($quotation);  // Works ✓
```

### Testing Status Transitions

```php
// Start with draft, test transition
$invoice = Invoice::factory()->draft()->create();
// ... test send action

$invoice = Invoice::factory()->sent()->create();
// ... test payment action

$invoice = Invoice::factory()->paid()->create();
// ... test void action
```
