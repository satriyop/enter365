---
adr: "0013"
title: "Multi-Warehouse Inventory"
status: accepted
date: 2024-11-01
deciders: [Product Team]
tags: [inventory, domain]
related_adrs: [0014, 0022]
related_modules: [inventory]
impact: medium
---

# ADR-0013: Multi-Warehouse Inventory

## AI Agent Quick Reference

**Use this ADR when:**
- Working with stock/inventory
- Implementing warehouse transfers
- Understanding stock tracking
- Building inventory reports

**Key takeaway:** Stock is tracked per product per warehouse using `ProductStock` model.

---

## Decision

Inventory is tracked at the product-warehouse level, allowing multiple storage locations.

---

## Implementation

### Data Model

```
Product (1) ←→ (N) ProductStock ←→ (1) Warehouse
```

```php
// ProductStock - stock per product per warehouse
$table->foreignId('product_id');
$table->foreignId('warehouse_id');
$table->integer('quantity_on_hand');
$table->integer('quantity_reserved');      // For pending orders
$table->integer('quantity_available');     // on_hand - reserved
$table->integer('reorder_point');
$table->integer('reorder_quantity');
```

### Warehouse Types

| Type | Purpose |
|------|---------|
| `main` | Primary storage |
| `production` | Manufacturing floor |
| `transit` | In-transit goods |
| `quarantine` | Quality hold |

### Stock Movements

```php
// InventoryMovement records all stock changes
$table->foreignId('product_id');
$table->foreignId('warehouse_id');
$table->string('movement_type');      // purchase, sales, transfer, adjustment
$table->integer('quantity');          // Positive or negative
$table->morphs('source');             // GRN, Invoice, StockOpname, etc.
```

### Movement Types

| Type | Quantity | Source |
|------|----------|--------|
| `purchase` | + | GoodsReceiptNote |
| `sales` | - | DeliveryOrder |
| `transfer_out` | - | WarehouseTransfer |
| `transfer_in` | + | WarehouseTransfer |
| `adjustment` | ± | StockOpname |
| `work_order` | - | MaterialRequisition |
| `production` | + | WorkOrder (finished) |

### Querying Stock

```php
// Available stock for a product
$stock = ProductStock::where('product_id', $productId)
    ->where('warehouse_id', $warehouseId)
    ->first();

$available = $stock->quantity_available;

// Total across all warehouses
$totalStock = ProductStock::where('product_id', $productId)
    ->sum('quantity_on_hand');
```

---

## References

- [ADR-0014: Inventory Costing Methods](./0014-inventory-costing-methods.md)
- [ADR-0022: Stock Opname Variance](./0022-stock-opname-variance.md)
