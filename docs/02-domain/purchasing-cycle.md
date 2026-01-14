---
section: domain
title: "Purchasing Cycle"
order: 3
entities: [PurchaseOrder, PurchaseOrderItem, GoodsReceiptNote, GoodsReceiptNoteItem, Bill, BillItem, PurchaseReturn]
services: [PurchaseOrderService, GoodsReceiptNoteService, PurchaseReturnService]
---

# Purchasing Cycle

> **Purchase Order → GRN → Bill → Payment flow**
>
> Complete procurement cycle from material need to vendor payment.

---

## AI Agent Quick Reference

**Use this document when:**
- Implementing purchasing features
- Debugging PO/GRN/Bill issues
- Understanding the procurement workflow
- Working with vendor payments

**Key models:** `PurchaseOrder`, `GoodsReceiptNote`, `Bill`, `PurchaseReturn`
**Key services:** `PurchaseOrderService`, `GoodsReceiptNoteService`

---

## Purchasing Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          PURCHASING CYCLE                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐             │
│   │ PURCHASE │───▶│   GRN    │───▶│   BILL   │───▶│ PAYMENT  │             │
│   │  ORDER   │    │(Received)│    │ (Posted) │    │  (Sent)  │             │
│   └────┬─────┘    └────┬─────┘    └────┬─────┘    └────┬─────┘             │
│        │               │               │               │                    │
│        │               │               │               │                    │
│   ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐             │
│   │ Approval │    │  Stock   │    │ Journal  │    │ Journal  │             │
│   │ Workflow │    │ Movement │    │  Entry   │    │  Entry   │             │
│   │          │    │  (In)    │    │ (DR:Exp) │    │(DR: AP)  │             │
│   │          │    │          │    │ (CR: AP) │    │(CR: Cash)│             │
│   └──────────┘    └──────────┘    └──────────┘    └──────────┘             │
│                                                                             │
│   SOURCES:                           OPTIONAL:                              │
│   ┌──────────────┐              ┌────────────────┐                         │
│   │     MRP      │              │PURCHASE RETURN │                         │
│   │ (Suggestions)│              │ (To Vendor)    │                         │
│   └──────────────┘              └────────────────┘                         │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Purchase Order (PO)

### Purpose
Formal request to vendor for goods/services.

### Statuses

| Status | Indonesian | Description |
|--------|------------|-------------|
| `draft` | Draf | Being edited |
| `submitted` | Diajukan | Pending approval |
| `approved` | Disetujui | Sent to vendor |
| `partial` | Sebagian | Partially received |
| `received` | Diterima | Fully received |
| `cancelled` | Dibatalkan | Cancelled |

### Workflow

```
Draft → Submit → Approve → Send to Vendor → Receive (GRN)
          │         │
          │         └─ Reject (with reason)
          │
          └─ Need revisions
```

### Key Fields

```php
// File: /app/Models/Accounting/PurchaseOrder.php

$table->string('po_number', 30);              // PO-202401-0001
$table->foreignId('contact_id');              // Vendor
$table->date('order_date');
$table->date('expected_date');                // Expected delivery
$table->string('status', 20);
$table->bigInteger('subtotal');
$table->decimal('tax_rate', 5, 2);            // 11.00 (PPN)
$table->bigInteger('tax_amount');
$table->bigInteger('total');
$table->text('notes')->nullable();
$table->text('shipping_address')->nullable();
```

### Sources of PO

1. **Manual Creation** - User creates PO directly
2. **MRP Suggestions** - System suggests PO based on demand
3. **Reorder Point** - Auto-suggest when stock below threshold

### Service Methods

```php
// File: /app/Services/Accounting/PurchaseOrderService.php

$poService->create($data);                    // Create draft
$poService->update($po, $data);
$poService->submit($po);                      // Submit for approval
$poService->approve($po);                     // Approve
$poService->reject($po, $reason);
$poService->cancel($po);
$poService->createFromMrpSuggestion($suggestion);
```

---

## Goods Receipt Note (GRN)

### Purpose
Document goods received from vendor against PO.

### Statuses

| Status | Indonesian | Description |
|--------|------------|-------------|
| `draft` | Draf | Being edited |
| `received` | Diterima | Goods received, stock updated |
| `inspecting` | Inspeksi | Quality check in progress |
| `accepted` | Diterima | Passed QC |
| `rejected` | Ditolak | Failed QC (return to vendor) |

### Workflow

```
PO Approved → Create GRN → Receive Goods → Update Stock → Create Bill
                                │
                                └─ Partial receiving allowed
```

### Key Fields

```php
// File: /app/Models/Accounting/GoodsReceiptNote.php

$table->string('grn_number', 30);             // GRN-202401-0001
$table->foreignId('purchase_order_id');
$table->foreignId('warehouse_id');            // Receiving warehouse
$table->date('received_date');
$table->string('status', 20);
$table->string('received_by')->nullable();    // Staff name
$table->text('notes')->nullable();
```

### Stock Impact

When GRN is completed:
- Stock added to warehouse
- InventoryMovement created (type: `purchase`)
- ProductStock increased
- Updates PO received quantities

### Partial Receiving

```php
// PO has 100 units
// GRN #1 receives 60 units → PO status: 'partial'
// GRN #2 receives 40 units → PO status: 'received'
```

### Service Methods

```php
// File: /app/Services/Accounting/GoodsReceiptNoteService.php

$grnService->create($data);                   // Create from PO
$grnService->receive($grn);                   // Mark received, update stock
$grnService->createBill($grn);                // Generate bill from GRN
```

---

## Bill (Tagihan Vendor)

### Purpose
Vendor invoice to be paid.

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
Debit:  Inventory/Expense (varies)      Rp 10,000,000
Debit:  PPN Masukan (1-1300)            Rp  1,100,000
Credit: Accounts Payable (2-1100)       Rp 11,100,000
```

### Key Fields

```php
// File: /app/Models/Accounting/Bill.php

$table->string('bill_number', 30);            // BILL-202401-0001
$table->foreignId('contact_id');              // Vendor
$table->foreignId('purchase_order_id')->nullable();
$table->foreignId('grn_id')->nullable();
$table->string('vendor_invoice_number');      // Vendor's invoice #
$table->date('bill_date');
$table->date('due_date');
$table->bigInteger('subtotal');
$table->bigInteger('tax_amount');
$table->bigInteger('total');
$table->bigInteger('amount_paid');
$table->bigInteger('balance');
```

---

## Payment to Vendor

### Journal Entry

```
Debit:  Accounts Payable (2-1100)       Rp 11,100,000
Credit: Cash/Bank (1-1001/1-1002)       Rp 11,100,000
```

### Key Fields

```php
$table->string('payment_number');             // PAY-202401-0001
$table->foreignId('contact_id');              // Vendor
$table->foreignId('bill_id');
$table->string('payment_type');               // 'send'
$table->string('payment_method');             // transfer, check
$table->bigInteger('amount');
$table->date('payment_date');
```

---

## Purchase Return (Retur Pembelian)

### Purpose
Return goods to vendor (defective, wrong items, excess).

### Flow

```
Identify Issue → Create Purchase Return → Ship to Vendor → Adjust AP
```

### Journal Entry

```
Debit:  Accounts Payable (2-1100)       Rp 1,110,000
Credit: Inventory (1-3000)              Rp 1,000,000
Credit: PPN Masukan (1-1300)            Rp   110,000
```

### Stock Impact

- Stock removed from warehouse
- InventoryMovement created (type: `purchase_return`)
- ProductStock decreased

---

## MRP Integration

MRP (Material Requirements Planning) automatically suggests POs:

```php
// MRP calculates demand from:
// 1. Work Orders (BOM explosion)
// 2. Sales forecasts
// 3. Safety stock requirements

$mrpService->run();

// Generates MrpSuggestion records:
// - Product: MCB 16A
// - Required Qty: 100
// - Current Stock: 20
// - Suggested PO Qty: 80
// - Suggested Vendor: PT Schneider

// User can convert to PO:
$poService->createFromMrpSuggestion($suggestion);
```

**See:** [Manufacturing](./manufacturing.md)

---

## API Endpoints

### Purchase Orders

```
GET    /api/v1/purchase-orders               # List
POST   /api/v1/purchase-orders               # Create
GET    /api/v1/purchase-orders/{id}          # Show
PUT    /api/v1/purchase-orders/{id}          # Update
DELETE /api/v1/purchase-orders/{id}          # Delete

POST   /api/v1/purchase-orders/{id}/submit   # Submit
POST   /api/v1/purchase-orders/{id}/approve  # Approve
POST   /api/v1/purchase-orders/{id}/cancel   # Cancel
```

### GRN

```
GET    /api/v1/goods-receipt-notes
POST   /api/v1/goods-receipt-notes           # Create from PO
GET    /api/v1/goods-receipt-notes/{id}
PUT    /api/v1/goods-receipt-notes/{id}

POST   /api/v1/goods-receipt-notes/{id}/receive    # Receive & update stock
POST   /api/v1/goods-receipt-notes/{id}/create-bill # Generate bill
```

### Bills

```
GET    /api/v1/bills
POST   /api/v1/bills
GET    /api/v1/bills/{id}
PUT    /api/v1/bills/{id}
DELETE /api/v1/bills/{id}

POST   /api/v1/bills/{id}/post               # Post (create JE)
POST   /api/v1/bills/{id}/void               # Void
```

---

## Common Queries

```php
// Pending POs (approved but not fully received)
PurchaseOrder::whereIn('status', ['approved', 'partial'])
    ->get();

// Unpaid bills
Bill::where('status', 'posted')
    ->where('balance', '>', 0)
    ->get();

// Overdue payables
Bill::where('due_date', '<', now())
    ->where('balance', '>', 0)
    ->get();

// Total payables
Bill::where('status', 'posted')
    ->sum('balance');

// GRNs pending bill creation
GoodsReceiptNote::where('status', 'received')
    ->whereDoesntHave('bill')
    ->get();
```

---

## Three-Way Matching

For audit/compliance, verify:
1. **PO** - What was ordered
2. **GRN** - What was received
3. **Bill** - What vendor is charging

```php
// Quantities should match
$poQty = $purchaseOrder->items->sum('quantity');
$grnQty = $grn->items->sum('quantity_received');
$billQty = $bill->items->sum('quantity');

// Prices should match
$poPrice = $purchaseOrder->total;
$billPrice = $bill->total;

if ($poPrice !== $billPrice) {
    // Flag for review
}
```

---

## Related Documentation

- [ADR-0021: GRN Multi-Step Workflow](../08-adr/0021-grn-workflow.md)
- [ADR-0017: MRP Demand Calculation](../08-adr/0017-mrp-demand-calculation.md)
- [Sales Cycle](./sales-cycle.md)
- [Manufacturing](./manufacturing.md)
