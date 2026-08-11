---
status: open
priority: P1
persona: Produksi
---

# Assert finished-goods stock on Work Order complete

## Problem

`FinishedGoodsHandler` exists under  
`app/Domain/Manufacturing/WorkOrders/Handlers/FinishedGoodsHandler.php`  
but `WorkflowIntegrationTest` manufacturing cycle only asserts **raw material** consume, not **FG** `ProductStock` increase.

## Acceptance

- [ ] Integration test: after `WorkOrderService::complete()`, FG product quantity increases by completed qty
- [ ] Inventory movement TYPE_IN (or equivalent) for FG
- [ ] If accounting strategy posts WIP/FG JE, assert balanced lines
- [ ] Test fails if handler disabled/skipped

## Related code

- `app/Services/Manufacturing/WorkOrderService.php` (`complete`)
- `app/Domain/Manufacturing/WorkOrders/Handlers/*`
- `tests/Feature/Integration/WorkflowIntegrationTest.php` (manufacturing describe)
