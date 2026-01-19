# Inventory Management

> **Multi-warehouse inventory tracking, stock movements, and physical counting**
>
> This document covers the inventory domain including products, warehouses, stock movements, valuation, and stock opname.

---

## Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    INVENTORY FLOW                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────┐                              ┌──────────┐              │
│  │ Purchase │ ──► Stock In ──────────────► │          │              │
│  │  (GRN)   │                              │          │              │
│  └──────────┘                              │          │              │
│                                            │ Warehouse│              │
│  ┌──────────┐                              │  Stock   │              │
│  │   Work   │ ◄── Material Issue ◄──────── │          │              │
│  │  Order   │                              │          │              │
│  │          │ ──► Finished Goods ────────► │          │              │
│  └──────────┘                              │          │              │
│                                            │          │              │
│  ┌──────────┐                              │          │              │
│  │   Sales  │ ◄── Stock Out ◄───────────── │          │              │
│  │   (DO)   │                              │          │              │
│  └──────────┘                              └──────────┘              │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Domain Models

### Product

The central catalog item.

| Field | Type | Description |
|-------|------|-------------|
| `code` | string | SKU/Part number (unique) |
| `name` | string | Product name |
| `category_id` | int | Product category |
| `type` | enum | `goods`, `service`, `asset` |
| `unit` | string | UoM (pcs, kg, m, etc.) |
| `cost_price` | int | Standard cost (IDR) |
| `sell_price` | int | Default sell price (IDR) |
| `min_stock` | int | Reorder point |
| `max_stock` | int | Maximum stock level |
| `lead_time_days` | int | Procurement lead time |

**Product Types:**
- `goods` - Physical inventory items
- `service` - Non-inventory services
- `asset` - Fixed assets

### Warehouse

Storage location for inventory.

| Field | Type | Description |
|-------|------|-------------|
| `code` | string | Warehouse code |
| `name` | string | Warehouse name |
| `address` | string | Physical location |
| `is_default` | bool | Default for transactions |
| `is_active` | bool | Active status |

### ProductStock

Stock level per product per warehouse.

| Field | Type | Description |
|-------|------|-------------|
| `product_id` | int | Product reference |
| `warehouse_id` | int | Warehouse reference |
| `quantity` | int | Current quantity |
| `reserved_quantity` | int | Reserved for orders |
| `available_quantity` | computed | quantity - reserved |

### InventoryMovement

Every stock change is recorded.

| Field | Type | Description |
|-------|------|-------------|
| `product_id` | int | Product reference |
| `warehouse_id` | int | Warehouse reference |
| `type` | enum | Movement type |
| `quantity` | int | Quantity (+ or -) |
| `reference_type` | string | Source document type |
| `reference_id` | int | Source document ID |
| `unit_cost` | int | Cost at time of movement |
| `notes` | string | Movement notes |

**Movement Types:**
- `stock_in` - Goods received
- `stock_out` - Goods shipped
- `adjustment` - Manual adjustment
- `transfer_in` - Transfer received
- `transfer_out` - Transfer sent
- `production_in` - Finished goods from WO
- `production_out` - Materials to WO
- `return_in` - Customer return received
- `return_out` - Supplier return sent

---

## Stock Operations

### Stock In (Receiving)

```
POST /api/v1/inventory/stock-in
```

```json
{
  "product_id": 1,
  "warehouse_id": 1,
  "quantity": 100,
  "unit_cost": 50000,
  "reference_type": "goods_receipt_note",
  "reference_id": 123,
  "notes": "PO-2601-0001 received"
}
```

**Automatic Triggers:**
- GRN completion
- Purchase return reversal
- Production output

### Stock Out (Shipping)

```
POST /api/v1/inventory/stock-out
```

```json
{
  "product_id": 1,
  "warehouse_id": 1,
  "quantity": 50,
  "reference_type": "delivery_order",
  "reference_id": 456,
  "notes": "DO-2601-0001 shipped"
}
```

**Automatic Triggers:**
- Delivery order shipment
- Material requisition issue
- Sales return reversal

### Stock Transfer

```
POST /api/v1/inventory/transfer
```

```json
{
  "product_id": 1,
  "from_warehouse_id": 1,
  "to_warehouse_id": 2,
  "quantity": 25,
  "notes": "Transfer to branch"
}
```

**Creates two movements:**
1. `transfer_out` from source warehouse
2. `transfer_in` to destination warehouse

### Stock Adjustment

```
POST /api/v1/inventory/adjust
```

```json
{
  "product_id": 1,
  "warehouse_id": 1,
  "adjustment_type": "increase", // or "decrease", "set"
  "quantity": 5,
  "reason": "Found extra stock during count",
  "notes": "Stock opname adjustment"
}
```

---

## Inventory Valuation

### Costing Methods

| Method | Configuration | Use Case |
|--------|---------------|----------|
| **FIFO** | `inventory_method: fifo` | General merchandise |
| **Average** | `inventory_method: average` | High-volume items |
| **Specific** | `inventory_method: specific` | Serialized items |

### Valuation Report

```
GET /api/v1/inventory/valuation
```

**Response:**
```json
{
  "as_of_date": "2026-01-19",
  "total_value": 1500000000,
  "items": [
    {
      "product_id": 1,
      "product_code": "MCB-16A",
      "product_name": "MCB 16A 1P",
      "quantity": 500,
      "unit_cost": 45000,
      "total_value": 22500000
    }
  ]
}
```

### COGS Recognition

```
┌─────────────────────────────────────────────────────────────────────┐
│                    COGS RECOGNITION STRATEGIES                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  On Invoice (default)                                                 │
│  └─► COGS recorded when invoice posted                               │
│                                                                       │
│  On Delivery                                                          │
│  └─► COGS recorded when goods shipped                                │
│                                                                       │
│  Manual                                                               │
│  └─► COGS recorded via manual journal entry                          │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Stock Opname (Physical Count)

### Workflow

```
┌─────────┐     ┌──────────┐     ┌────────┐     ┌──────────┐
│  Draft  │ ──► │ Counting │ ──► │ Review │ ──► │ Approved │
└─────────┘     └──────────┘     └────────┘     └──────────┘
                                      │
                                      ▼
                              ┌──────────────┐
                              │  Adjustments │
                              │   Created    │
                              └──────────────┘
```

### Create Stock Opname

```
POST /api/v1/stock-opnames
```

```json
{
  "warehouse_id": 1,
  "opname_date": "2026-01-19",
  "notes": "Monthly stock count January 2026"
}
```

### Generate Count Items

```
POST /api/v1/stock-opnames/{id}/generate-items
```

Automatically creates count items for all products with stock in the warehouse.

### Start Counting

```
POST /api/v1/stock-opnames/{id}/start-counting
```

Changes status to `counting`, allows quantity updates.

### Update Count

```
PUT /api/v1/stock-opnames/{id}/items/{item_id}
```

```json
{
  "counted_quantity": 95
}
```

### Submit for Review

```
POST /api/v1/stock-opnames/{id}/submit-review
```

### Variance Report

```
GET /api/v1/stock-opnames/{id}/variance-report
```

```json
{
  "opname_id": 1,
  "total_items": 50,
  "items_with_variance": 5,
  "total_variance_value": -500000,
  "items": [
    {
      "product_code": "MCB-16A",
      "product_name": "MCB 16A 1P",
      "system_quantity": 100,
      "counted_quantity": 95,
      "variance": -5,
      "unit_cost": 45000,
      "variance_value": -225000
    }
  ]
}
```

### Approve

```
POST /api/v1/stock-opnames/{id}/approve
```

**On approval:**
1. Creates adjustment movements for all variances
2. Updates ProductStock quantities
3. Creates journal entry for inventory adjustments

---

## Stock Card

Transaction history for a product:

```
GET /api/v1/inventory/stock-card/{product_id}
```

```json
{
  "product": {
    "id": 1,
    "code": "MCB-16A",
    "name": "MCB 16A 1P"
  },
  "opening_balance": 100,
  "closing_balance": 95,
  "movements": [
    {
      "date": "2026-01-15",
      "type": "stock_in",
      "quantity": 50,
      "balance": 150,
      "reference": "GRN-2601-0001",
      "notes": "Purchase from PT ABC"
    },
    {
      "date": "2026-01-18",
      "type": "stock_out",
      "quantity": -55,
      "balance": 95,
      "reference": "DO-2601-0005",
      "notes": "Sale to PT XYZ"
    }
  ]
}
```

---

## Low Stock Alerts

```
GET /api/v1/products-low-stock
```

Returns products where `quantity <= min_stock`.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "MCB-16A",
      "name": "MCB 16A 1P",
      "current_stock": 10,
      "min_stock": 50,
      "shortage": 40,
      "lead_time_days": 7
    }
  ]
}
```

---

## Integration with Other Modules

### Purchasing → Inventory

```
PO Created → GRN Created → GRN Completed → Stock In
```

### Sales → Inventory

```
Invoice Posted → DO Created → DO Shipped → Stock Out
```

### Manufacturing → Inventory

```
WO Started → MR Issued → Materials Out
WO Completed → Finished Goods In
```

---

## Accounting Integration

### Inventory Accounts

| Account | Code | Purpose |
|---------|------|---------|
| Inventory | 1-1200 | Stock value |
| COGS | 5-1001 | Cost of goods sold |
| Inventory Adjustment | 5-9001 | Variance from opname |

### Journal Entries

**Stock In (Purchase):**
```
Debit:  1-1200 Inventory          100,000
Credit: 2-1001 AP                        100,000
```

**Stock Out (Sale):**
```
Debit:  5-1001 COGS               60,000
Credit: 1-1200 Inventory                 60,000
```

**Adjustment (Shortage):**
```
Debit:  5-9001 Inventory Adjustment  5,000
Credit: 1-1200 Inventory                  5,000
```

---

## Related Code

| Component | Path |
|-----------|------|
| Models | `app/Models/Inventory/` |
| Services | `app/Services/Inventory/InventoryService.php` |
| Stock Opname | `app/Services/Inventory/StockOpnameService.php` |
| Controllers | `app/Http/Controllers/Api/V1/InventoryController.php` |

---

## Related Documentation

- [Manufacturing Domain](/docs/02-domain/manufacturing.md)
- [Purchasing Cycle](/docs/02-domain/purchasing-cycle.md)
- [Sales Cycle](/docs/02-domain/sales-cycle.md)
- [Business Rules - Inventory Valuation](/docs/06-business-rules/README.md#inventory-valuation)
- [ADR-0013: Multi-warehouse Inventory](/docs/08-adr/0013-multi-warehouse-inventory.md)
- [ADR-0014: Inventory Costing Methods](/docs/08-adr/0014-inventory-costing-methods.md)
