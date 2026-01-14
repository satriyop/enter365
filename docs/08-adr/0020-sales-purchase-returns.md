---
adr: "0020"
title: "Sales/Purchase Returns Flow"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [sales, purchasing]
related_adrs: [0013, 0011]
related_modules: [sales, purchasing]
impact: medium
---

# ADR-0020: Sales/Purchase Returns Flow

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing return features
- Understanding credit/debit notes
- Working with return journal entries
- Handling stock adjustments for returns

**Key takeaway:** Returns create credit/debit notes, adjust AR/AP, and reverse inventory movements.

---

## Decision

Implement separate return documents (Sales Return, Purchase Return) that create credit notes, adjust receivables/payables, and reverse inventory.

---

## Implementation

### Sales Return (Retur Penjualan)

**Purpose**: Customer returns goods, we issue credit note.

```php
// SalesReturn
$table->string('return_number');            // SR-202401-0001
$table->foreignId('invoice_id');            // Original invoice
$table->foreignId('contact_id');
$table->date('return_date');
$table->string('reason');
$table->bigInteger('subtotal');
$table->bigInteger('tax_amount');
$table->bigInteger('total');
```

**Journal Entry:**
```
DR Sales Returns (contra-revenue)   Rp 1,000,000
DR PPN Keluaran                     Rp   110,000
CR Accounts Receivable              Rp 1,110,000
```

**Inventory Impact:**
```php
// Stock returned to warehouse
InventoryMovement::create([
    'product_id' => $item->product_id,
    'warehouse_id' => $warehouseId,
    'movement_type' => 'sales_return',
    'quantity' => $item->quantity,  // Positive (stock in)
]);
```

### Purchase Return (Retur Pembelian)

**Purpose**: We return goods to vendor, reduce payable.

```php
// PurchaseReturn
$table->string('return_number');            // PR-202401-0001
$table->foreignId('bill_id');               // Original bill
$table->foreignId('contact_id');            // Vendor
$table->date('return_date');
$table->string('reason');
$table->bigInteger('total');
```

**Journal Entry:**
```
DR Accounts Payable                 Rp 1,110,000
CR Inventory                        Rp 1,000,000
CR PPN Masukan                      Rp   110,000
```

**Inventory Impact:**
```php
InventoryMovement::create([
    'movement_type' => 'purchase_return',
    'quantity' => -$item->quantity,  // Negative (stock out)
]);
```

### Return Workflow

```
Original Document → Create Return → Approve → Post
                                      │
                       ┌──────────────┼──────────────┐
                       ▼              ▼              ▼
               Update AR/AP    Update Stock    Journal Entry
```

---

## References

- [ADR-0013: Multi-Warehouse Inventory](./0013-multi-warehouse-inventory.md)
- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
