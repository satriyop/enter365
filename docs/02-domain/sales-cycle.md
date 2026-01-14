---
section: domain
title: "Sales Cycle"
order: 2
entities: [Quotation, QuotationItem, Invoice, InvoiceItem, Payment, DeliveryOrder, DownPayment, SalesReturn]
services: [QuotationService, DeliveryOrderService, DownPaymentService, SalesReturnService]
---

# Sales Cycle

> **Quotation → Invoice → Payment flow**
>
> Complete sales cycle from customer inquiry to cash collection.

---

## AI Agent Quick Reference

**Use this document when:**
- Implementing sales features
- Debugging quotation/invoice issues
- Understanding the sales workflow
- Working with payments and down payments

**Key models:** `Quotation`, `Invoice`, `Payment`, `DeliveryOrder`, `DownPayment`
**Key services:** `QuotationService`, `DownPaymentService`

---

## Sales Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            SALES CYCLE                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐             │
│   │ QUOTATION│───▶│ INVOICE  │───▶│ DELIVERY │───▶│ PAYMENT  │             │
│   │  (Draft) │    │ (Posted) │    │  ORDER   │    │(Received)│             │
│   └────┬─────┘    └────┬─────┘    └────┬─────┘    └────┬─────┘             │
│        │               │               │               │                    │
│        │               │               │               │                    │
│   ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐             │
│   │ Optional │    │ Journal  │    │  Stock   │    │ Journal  │             │
│   │ Variants │    │  Entry   │    │ Movement │    │  Entry   │             │
│   │ (Budget/ │    │ (DR: AR) │    │ (Out)    │    │(DR: Cash)│             │
│   │ Standard/│    │ (CR:Rev) │    │          │    │(CR: AR)  │             │
│   │ Premium) │    │          │    │          │    │          │             │
│   └──────────┘    └──────────┘    └──────────┘    └──────────┘             │
│                                                                             │
│   OPTIONAL FLOWS:                                                           │
│   ┌──────────────┐    ┌──────────────┐                                     │
│   │ DOWN PAYMENT │    │ SALES RETURN │                                     │
│   │ (Before Inv) │    │ (After Inv)  │                                     │
│   └──────────────┘    └──────────────┘                                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Quotation (Penawaran)

### Purpose
Price quote sent to customer before sale confirmation.

### Statuses

| Status | Indonesian | Description |
|--------|------------|-------------|
| `draft` | Draf | Being edited |
| `submitted` | Diajukan | Pending approval |
| `approved` | Disetujui | Ready for customer |
| `rejected` | Ditolak | Not approved |
| `expired` | Kedaluwarsa | Past valid_until date |
| `converted` | Dikonversi | Became invoice |

### Workflow

```
Draft → Submit → Approve → Send to Customer → Convert to Invoice
         │          │
         │          └─ Reject (with reason)
         │
         └─ Revise (creates new revision)
```

### Key Fields

```php
// File: /app/Models/Accounting/Quotation.php

$table->string('quotation_number', 30);      // QUO-202401-0001
$table->unsignedTinyInteger('revision');      // 0, 1, 2...
$table->foreignId('contact_id');              // Customer
$table->date('quotation_date');
$table->date('valid_until');                  // Expiry date
$table->string('status', 20);
$table->bigInteger('subtotal');               // Before tax
$table->decimal('tax_rate', 5, 2);            // 11.00 (PPN)
$table->bigInteger('tax_amount');
$table->bigInteger('total');
```

### Multi-Option Quotations (Killer Feature)

Allow customers to choose between Budget/Standard/Premium options:

```php
// Create variant group with 3 BOMs
$group = BomVariantGroup::create(['name' => 'Panel MDP 100A']);

// Each BOM uses different brand components
$budgetBom = Bom::create([...]);   // Siemens - Rp 45M
$standardBom = Bom::create([...]);  // Schneider - Rp 52M
$premiumBom = Bom::create([...]);   // ABB - Rp 68M

// Quotation shows all options
$quotation = QuotationVariantOption::create([
    'quotation_id' => $quotation->id,
    'bom_id' => $budgetBom->id,
    'display_name' => 'Budget (Siemens)',
    'selling_price' => 56_250_000_00,
]);
```

**See:** [ADR-0009: BOM Variant Groups](../08-adr/0009-bom-variant-groups.md)

### Service Methods

```php
// File: /app/Services/Accounting/QuotationService.php

$quotationService->create($data);           // Create draft
$quotationService->update($quotation, $data);
$quotationService->submit($quotation);      // Submit for approval
$quotationService->approve($quotation);     // Approve
$quotationService->reject($quotation, $reason);
$quotationService->revise($quotation);      // Create new revision
$quotationService->convertToInvoice($quotation);
$quotationService->duplicate($quotation);   // Copy to new
```

---

## Invoice (Faktur)

### Purpose
Billing document sent to customer after sale confirmation.

### Statuses

| Status | Indonesian | Description |
|--------|------------|-------------|
| `draft` | Draf | Being edited |
| `posted` | Terposting | Creates journal entry |
| `partial` | Sebagian | Partially paid |
| `paid` | Lunas | Fully paid |
| `overdue` | Jatuh Tempo | Past due date |
| `void` | Batal | Cancelled |

### Journal Entry (on Post)

```
Debit:  Accounts Receivable (1-1100)    Rp 11,100,000
Credit: Sales Revenue (4-1001)          Rp 10,000,000
Credit: PPN Keluaran (2-1200)           Rp  1,100,000
```

### Key Fields

```php
$table->string('invoice_number', 30);         // INV-202401-0001
$table->foreignId('contact_id');
$table->foreignId('quotation_id')->nullable(); // Source quotation
$table->date('invoice_date');
$table->date('due_date');                      // invoice_date + terms
$table->bigInteger('subtotal');
$table->bigInteger('tax_amount');
$table->bigInteger('total');
$table->bigInteger('amount_paid');             // Payments received
$table->bigInteger('balance');                 // total - amount_paid
```

---

## Down Payment (Uang Muka)

### Purpose
Advance payment before invoice (common in Indonesia: 30-50% DP).

### Flow

```
Customer Pays DP → Record DownPayment → Apply to Invoice Later
```

### Key Fields

```php
$table->string('down_payment_number');        // DP-202401-0001
$table->foreignId('contact_id');
$table->bigInteger('amount');
$table->bigInteger('applied_amount');         // Used on invoices
$table->bigInteger('remaining_amount');       // Available to apply
```

### Application

```php
// Apply DP to invoice
$downPaymentService->applyToInvoice($downPayment, $invoice, $amount);

// Creates DownPaymentApplication record
// Reduces invoice balance
// Creates adjustment journal entry
```

---

## Delivery Order (Surat Jalan)

### Purpose
Shipping document accompanying goods delivery.

### Flow

```
Invoice Created → Create DO → Ship Goods → Stock Movement
```

### Key Fields

```php
$table->string('delivery_order_number');      // DO-202401-0001
$table->foreignId('invoice_id');
$table->date('delivery_date');
$table->string('shipping_address');
$table->string('received_by')->nullable();    // Customer signature
```

### Stock Impact

When DO is completed:
- Stock out from warehouse
- InventoryMovement created (type: `sales`)
- ProductStock reduced

---

## Payment (Pembayaran)

### Purpose
Record customer payment against invoice.

### Types

| Type | Description |
|------|-------------|
| `receive` | Payment from customer |
| `send` | Payment to vendor (purchasing) |

### Journal Entry

```
Debit:  Cash/Bank (1-1001/1-1002)       Rp 11,100,000
Credit: Accounts Receivable (1-1100)    Rp 11,100,000
```

### Key Fields

```php
$table->string('payment_number');             // RCV-202401-0001
$table->foreignId('contact_id');
$table->foreignId('invoice_id')->nullable();  // For AR
$table->foreignId('bill_id')->nullable();     // For AP
$table->string('payment_type');               // receive, send
$table->string('payment_method');             // cash, transfer, check
$table->bigInteger('amount');
$table->date('payment_date');
```

---

## Sales Return (Retur Penjualan)

### Purpose
Handle customer returns and issue credit notes.

### Flow

```
Customer Returns Goods → Create Sales Return → Credit Note → Adjust AR
```

### Journal Entry

```
Debit:  Sales Returns (4-1002)          Rp 1,000,000
Debit:  PPN Keluaran (2-1200)           Rp   110,000
Credit: Accounts Receivable (1-1100)    Rp 1,110,000
```

### Stock Impact

- Stock returned to warehouse
- InventoryMovement created (type: `sales_return`)
- ProductStock increased

---

## API Endpoints

### Quotations

```
GET    /api/v1/quotations                    # List
POST   /api/v1/quotations                    # Create
GET    /api/v1/quotations/{id}               # Show
PUT    /api/v1/quotations/{id}               # Update
DELETE /api/v1/quotations/{id}               # Delete

POST   /api/v1/quotations/{id}/submit        # Submit for approval
POST   /api/v1/quotations/{id}/approve       # Approve
POST   /api/v1/quotations/{id}/reject        # Reject
POST   /api/v1/quotations/{id}/convert       # Convert to invoice
POST   /api/v1/quotations/{id}/revise        # Create revision
POST   /api/v1/quotations/{id}/duplicate     # Copy
```

### Invoices

```
GET    /api/v1/invoices
POST   /api/v1/invoices
GET    /api/v1/invoices/{id}
PUT    /api/v1/invoices/{id}
DELETE /api/v1/invoices/{id}

POST   /api/v1/invoices/{id}/post            # Post (create JE)
POST   /api/v1/invoices/{id}/void            # Void
```

### Payments

```
GET    /api/v1/payments
POST   /api/v1/payments
GET    /api/v1/payments/{id}
DELETE /api/v1/payments/{id}
```

---

## Common Queries

```php
// Unpaid invoices for a customer
Invoice::where('contact_id', $contactId)
    ->where('status', 'posted')
    ->where('balance', '>', 0)
    ->get();

// Overdue invoices
Invoice::where('due_date', '<', now())
    ->where('balance', '>', 0)
    ->get();

// Quotations expiring soon
Quotation::where('status', 'approved')
    ->where('valid_until', '<=', now()->addDays(7))
    ->get();

// Total receivables
Invoice::where('status', 'posted')
    ->sum('balance');
```

---

## Related Documentation

- [ADR-0009: BOM Variant Groups](../08-adr/0009-bom-variant-groups.md)
- [ADR-0019: Down Payment Application](../08-adr/0019-down-payment-application.md)
- [Purchasing Cycle](./purchasing-cycle.md)
- [Indonesian Context](./indonesian-context.md)
