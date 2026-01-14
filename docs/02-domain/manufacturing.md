---
section: domain
title: "Manufacturing"
order: 4
entities: [Bom, BomItem, BomTemplate, BomVariantGroup, WorkOrder, WorkOrderItem, MaterialRequisition, MaterialConsumption, MrpRun, MrpDemand, MrpSuggestion]
services: [BomService, BomTemplateService, BomVariantGroupService, WorkOrderService, MaterialRequisitionService, MrpService]
---

# Manufacturing

> **BOM → Work Order → MRP flow**
>
> Complete manufacturing cycle for electrical panel production.

---

## AI Agent Quick Reference

**Use this document when:**
- Implementing BOM features
- Working with work orders
- Understanding MRP calculations
- Building multi-brand quotations (killer feature)

**Key models:** `Bom`, `BomVariantGroup`, `WorkOrder`, `MrpRun`
**Key services:** `BomService`, `BomVariantGroupService`, `MrpService`

---

## Manufacturing Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         MANUFACTURING CYCLE                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐             │
│   │   BOM    │───▶│  WORK    │───▶│ MATERIAL │───▶│ FINISHED │             │
│   │ (Recipe) │    │  ORDER   │    │REQUISITION│   │  GOODS   │             │
│   └────┬─────┘    └────┬─────┘    └────┬─────┘    └────┬─────┘             │
│        │               │               │               │                    │
│        │               │               │               │                    │
│   ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐             │
│   │ Variant  │    │Components│    │  Stock   │    │ Stock In │             │
│   │ Groups   │    │ Required │    │   Out    │    │ (Product)│             │
│   │(ABB/     │    │          │    │(Materials│    │          │             │
│   │Schneider/│    │          │    │          │    │          │             │
│   │Siemens)  │    │          │    │          │    │          │             │
│   └──────────┘    └──────────┘    └──────────┘    └──────────┘             │
│                                                                             │
│   MRP FLOW:                                                                 │
│   ┌──────────┐    ┌──────────┐    ┌──────────┐                             │
│   │  DEMAND  │───▶│   MRP    │───▶│ PURCHASE │                             │
│   │(WO + SO) │    │   RUN    │    │SUGGESTIONS│                            │
│   └──────────┘    └──────────┘    └──────────┘                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Bill of Materials (BOM)

### Purpose
Recipe defining components needed to build a finished product.

### Structure

```
Finished Product: Panel MDP 100A
├── MCB 3P 100A × 1
├── MCB 1P 16A × 12
├── Busbar Copper × 2m
├── Enclosure 800×600 × 1
├── Terminal Block × 24
├── Cable 2.5mm² × 50m
└── Labor: Assembly × 8 hours
```

### Key Fields

```php
// File: /app/Models/Accounting/Bom.php

$table->string('bom_number', 30);             // BOM-0001
$table->foreignId('product_id');              // Finished product
$table->string('name');
$table->text('description')->nullable();
$table->string('status', 20);                 // draft, active, obsolete
$table->bigInteger('material_cost');          // Sum of component costs
$table->bigInteger('labor_cost');
$table->bigInteger('overhead_cost');
$table->bigInteger('unit_cost');              // Total cost per unit
$table->integer('lead_time_days');            // Production time
```

### BOM Items

```php
// File: /app/Models/Accounting/BomItem.php

$table->foreignId('bom_id');
$table->foreignId('product_id');              // Component
$table->decimal('quantity', 15, 4);           // e.g., 2.5 meters
$table->string('unit', 20);                   // pcs, m, kg
$table->bigInteger('unit_cost');              // Cost per unit
$table->bigInteger('total_cost');             // quantity × unit_cost
$table->string('type', 20);                   // material, labor, overhead
$table->integer('sort_order');
```

### Item Types

| Type | Description | Example |
|------|-------------|---------|
| `material` | Physical components | MCB, cables, enclosure |
| `labor` | Work hours | Assembly, wiring |
| `overhead` | Indirect costs | Testing, packaging |

---

## BOM Variant Groups (Killer Feature)

### Purpose
Create Budget/Standard/Premium versions using different component brands.

### Why This Matters

Electrical panel customers want to compare:
- **Budget** (Siemens) - Lower cost
- **Standard** (Schneider) - Mid-range
- **Premium** (ABB) - High quality

Without this feature: Hours of manual spreadsheet work
With this feature: Minutes of automated calculation

### Structure

```
BomVariantGroup: "Panel MDP 100A"
├── BOM: Budget (Siemens)     → Rp 45,000,000
├── BOM: Standard (Schneider) → Rp 52,000,000
└── BOM: Premium (ABB)        → Rp 68,000,000
```

### Key Fields

```php
// File: /app/Models/Accounting/BomVariantGroup.php

$table->string('name');                       // "Panel MDP 100A"
$table->text('description')->nullable();
$table->foreignId('product_id');              // Finished product
$table->string('status', 20);                 // active, inactive
```

### Workflow

```php
// 1. Create variant group
$group = BomVariantGroup::create([
    'name' => 'Panel MDP 100A',
    'product_id' => $finishedProduct->id,
]);

// 2. Create Budget variant
$budgetBom = $bomService->create([
    'variant_group_id' => $group->id,
    'variant_name' => 'Budget',
    'variant_sort_order' => 1,
    'items' => [
        ['product_id' => $siemensMcb->id, 'quantity' => 12, ...],
        // ... Siemens components
    ],
]);

// 3. Duplicate and swap for Standard
$standardBom = $bomVariantGroupService->duplicateAsVariant(
    $group, $budgetBom, 'Standard'
);
// Then swap Siemens → Schneider using component cross-reference

// 4. Create quotation with all options
$quotation = $quotationService->createFromVariantGroup($group, [
    'contact_id' => $customer->id,
    'margin_percent' => 25,
]);
```

### Component Cross-Reference

Map equivalent components across brands:

```php
// ComponentStandard: "MCB 1P 16A 6kA"
// ComponentBrandMapping:
//   - Siemens 5SL6116-7 → Rp 85,000
//   - Schneider A9F74116 → Rp 95,000
//   - ABB S201-C16 → Rp 125,000
```

**See:** [ADR-0009: BOM Variant Groups](../08-adr/0009-bom-variant-groups.md)

---

## Work Order (Perintah Kerja)

### Purpose
Production order to manufacture a finished product.

### Statuses

| Status | Indonesian | Description |
|--------|------------|-------------|
| `draft` | Draf | Being planned |
| `released` | Dirilis | Ready to start |
| `in_progress` | Dikerjakan | Production ongoing |
| `completed` | Selesai | Finished goods ready |
| `cancelled` | Dibatalkan | Cancelled |

### Key Fields

```php
// File: /app/Models/Accounting/WorkOrder.php

$table->string('work_order_number', 30);      // WO-202401-0001
$table->foreignId('bom_id');
$table->foreignId('product_id');              // Finished product
$table->integer('quantity');                  // Units to produce
$table->date('planned_start_date');
$table->date('planned_end_date');
$table->date('actual_start_date')->nullable();
$table->date('actual_end_date')->nullable();
$table->string('status', 20);
$table->foreignId('project_id')->nullable();  // For project costing
```

### Workflow

```
Draft → Release → Start → [Material Requisition] → Complete
                    │
                    └─ Creates demand for MRP
```

### Service Methods

```php
// File: /app/Services/Accounting/WorkOrderService.php

$woService->create($data);                    // Create from BOM
$woService->release($workOrder);              // Mark ready
$woService->start($workOrder);                // Begin production
$woService->complete($workOrder);             // Finish production
$woService->cancel($workOrder);
```

---

## Material Requisition

### Purpose
Request materials from warehouse for production.

### Flow

```
Work Order Started → Create Material Requisition → Issue from Warehouse
```

### Key Fields

```php
$table->string('requisition_number');         // MR-202401-0001
$table->foreignId('work_order_id');
$table->foreignId('warehouse_id');
$table->date('requisition_date');
$table->string('status');                     // draft, issued, partial
```

### Stock Impact

- Materials issued from warehouse
- InventoryMovement created (type: `work_order`)
- ProductStock decreased for components

---

## MRP (Material Requirements Planning)

### Purpose
Calculate material needs and suggest purchase orders.

### MRP Calculation

```
Gross Requirements (from Work Orders + Sales Forecasts)
- Scheduled Receipts (pending POs)
- On-Hand Inventory
= Net Requirements

If Net Requirements > 0:
  → Generate Purchase Suggestion
```

### MRP Run

```php
// File: /app/Services/Accounting/MrpService.php

$mrpService->run([
    'planning_horizon_days' => 30,
    'include_safety_stock' => true,
]);

// Creates:
// 1. MrpRun record (header)
// 2. MrpDemand records (calculated needs)
// 3. MrpSuggestion records (what to buy)
```

### Key Entities

```php
// MrpRun - The calculation run
$table->timestamp('run_date');
$table->integer('planning_horizon_days');
$table->string('status');                     // running, completed, failed

// MrpDemand - Demand per product
$table->foreignId('mrp_run_id');
$table->foreignId('product_id');
$table->integer('gross_requirement');
$table->integer('scheduled_receipts');
$table->integer('on_hand');
$table->integer('net_requirement');

// MrpSuggestion - Purchase suggestions
$table->foreignId('mrp_run_id');
$table->foreignId('product_id');
$table->foreignId('suggested_vendor_id');
$table->integer('suggested_quantity');
$table->date('suggested_order_date');
$table->date('suggested_delivery_date');
```

### Converting Suggestions to PO

```php
// User reviews suggestions and converts to POs
$poService->createFromMrpSuggestion($suggestion);
```

**See:** [ADR-0017: MRP Demand Calculation](../08-adr/0017-mrp-demand-calculation.md)

---

## BOM Templates

### Purpose
Reusable templates for common panel configurations.

### Use Case

```
Template: "Standard MDP Configuration"
├── Base components (always included)
├── Optional: Surge protection
├── Optional: Earth leakage
└── Variable: Main breaker amperage (60A/100A/160A)

→ User selects options → Generates complete BOM
```

---

## API Endpoints

### BOMs

```
GET    /api/v1/boms                          # List
POST   /api/v1/boms                          # Create
GET    /api/v1/boms/{id}                     # Show
PUT    /api/v1/boms/{id}                     # Update
DELETE /api/v1/boms/{id}                     # Delete

POST   /api/v1/boms/{id}/duplicate           # Copy BOM
POST   /api/v1/boms/{id}/calculate-cost      # Recalculate costs
```

### BOM Variant Groups

```
GET    /api/v1/bom-variant-groups
POST   /api/v1/bom-variant-groups
GET    /api/v1/bom-variant-groups/{id}
PUT    /api/v1/bom-variant-groups/{id}

GET    /api/v1/bom-variant-groups/{id}/comparison  # Cost comparison
POST   /api/v1/bom-variant-groups/{id}/add-variant
```

### Work Orders

```
GET    /api/v1/work-orders
POST   /api/v1/work-orders
GET    /api/v1/work-orders/{id}
PUT    /api/v1/work-orders/{id}

POST   /api/v1/work-orders/{id}/release      # Release for production
POST   /api/v1/work-orders/{id}/start        # Start production
POST   /api/v1/work-orders/{id}/complete     # Complete production
```

### MRP

```
GET    /api/v1/mrp                           # Current status
POST   /api/v1/mrp/run                       # Execute MRP calculation
GET    /api/v1/mrp/suggestions               # View suggestions
POST   /api/v1/mrp/suggestions/{id}/convert  # Convert to PO
```

---

## Common Queries

```php
// Active BOMs for a product
Bom::where('product_id', $productId)
    ->where('status', 'active')
    ->get();

// Work orders in progress
WorkOrder::where('status', 'in_progress')
    ->with('bom', 'product')
    ->get();

// Pending material requisitions
MaterialRequisition::where('status', 'draft')
    ->get();

// Latest MRP suggestions
MrpSuggestion::where('mrp_run_id', $latestRun->id)
    ->with('product', 'suggestedVendor')
    ->get();
```

---

## Related Documentation

- [ADR-0009: BOM Variant Groups](../08-adr/0009-bom-variant-groups.md)
- [ADR-0017: MRP Demand Calculation](../08-adr/0017-mrp-demand-calculation.md)
- [ADR-0024: Material Consumption Tracking](../08-adr/0024-material-consumption-tracking.md)
- [Purchasing Cycle](./purchasing-cycle.md)
- [Sales Cycle](./sales-cycle.md)
