---
adr: "0023"
title: "Project Cost Allocation"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [projects, accounting]
related_adrs: [0011, 0018]
related_modules: [projects]
impact: medium
---

# ADR-0023: Project Cost Allocation

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing project features
- Tracking project costs
- Building profitability reports
- Allocating expenses to projects

**Key takeaway:** Costs and revenues are allocated to projects for profitability tracking.

---

## Decision

Implement project-based cost and revenue tracking for profitability analysis.

---

## Implementation

### Project Model

```php
$table->string('project_number');           // PRJ-202401-0001
$table->string('name');
$table->foreignId('contact_id');            // Customer
$table->date('start_date');
$table->date('end_date');
$table->bigInteger('contract_value');
$table->bigInteger('estimated_cost');
$table->bigInteger('actual_cost');          // Sum of ProjectCost
$table->bigInteger('actual_revenue');       // Sum of ProjectRevenue
$table->string('status');                   // planning, active, completed
```

### Cost Allocation

```php
// ProjectCost - costs allocated to project
$table->foreignId('project_id');
$table->string('cost_type');                // material, labor, subcontractor, overhead
$table->morphs('source');                   // WorkOrder, SubcontractorInvoice, etc.
$table->bigInteger('amount');
$table->date('cost_date');
$table->text('description');
```

### Cost Types

| Type | Source | Description |
|------|--------|-------------|
| `material` | WorkOrder | Materials consumed |
| `labor` | WorkOrder | Labor hours |
| `subcontractor` | SubcontractorInvoice | Outsourced work |
| `overhead` | Manual entry | Indirect costs |
| `other` | Manual entry | Miscellaneous |

### Revenue Allocation

```php
// ProjectRevenue
$table->foreignId('project_id');
$table->morphs('source');                   // Invoice, DownPayment
$table->bigInteger('amount');
$table->date('revenue_date');
```

### Profitability Calculation

```php
public function getProfitability(Project $project): array
{
    $revenue = $project->revenues()->sum('amount');
    $cost = $project->costs()->sum('amount');
    $profit = $revenue - $cost;
    $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

    return [
        'revenue' => $revenue,
        'cost' => $cost,
        'profit' => $profit,
        'margin_percent' => round($margin, 2),
        'vs_estimate' => $cost - $project->estimated_cost,
    ];
}
```

### Work Order Integration

```php
// When work order completes, allocate costs to project
public function complete(WorkOrder $workOrder): void
{
    if ($workOrder->project_id) {
        // Material cost
        ProjectCost::create([
            'project_id' => $workOrder->project_id,
            'cost_type' => 'material',
            'source_type' => WorkOrder::class,
            'source_id' => $workOrder->id,
            'amount' => $workOrder->material_cost,
        ]);

        // Labor cost
        ProjectCost::create([
            'project_id' => $workOrder->project_id,
            'cost_type' => 'labor',
            'source_type' => WorkOrder::class,
            'source_id' => $workOrder->id,
            'amount' => $workOrder->labor_cost,
        ]);
    }
}
```

---

## References

- [ADR-0018: Subcontractor Retention](./0018-subcontractor-retention.md)
- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
