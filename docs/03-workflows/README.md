---
section: workflows
title: "Business Workflows"
description: "Visual process flows for key business operations"
---

# Business Workflows

## AI Agent Quick Reference

**Use this section when:**
- Understanding end-to-end processes
- Implementing workflow features
- Debugging process issues
- Training users on operations

---

## Available Workflows

| Workflow | Domain | Key Entities |
|----------|--------|--------------|
| [Sales Workflow](./sales-workflow.md) | Sales | Quotation, Invoice, Payment |
| [Purchasing Workflow](./purchasing-workflow.md) | Purchasing | PO, GRN, Bill |
| [Manufacturing Workflow](./manufacturing-workflow.md) | Manufacturing | BOM, Work Order |
| [MRP Workflow](./mrp-workflow.md) | Manufacturing | MRP Run, Purchase Suggestions |

---

## Workflow Legend

```
┌─────────┐     Document/Entity
│         │
└─────────┘

    ───▶        Flow direction

    ◇           Decision point

   [Action]     User action

  {Auto}        System action
```

---

## Status Conventions

All workflows use consistent status patterns:

| Status | Meaning | Can Edit? |
|--------|---------|-----------|
| draft | Being created | Yes |
| pending | Awaiting approval | No |
| approved | Ready to process | No |
| in_progress | Being worked on | Limited |
| completed | Finished | No |
| cancelled | Voided | No |

---

## Cross-References

- [Sales Cycle Domain](../02-domain/sales-cycle.md)
- [Purchasing Cycle Domain](../02-domain/purchasing-cycle.md)
- [Manufacturing Domain](../02-domain/manufacturing.md)
- [Service Layer](../01-architecture/service-layer.md)

