---
status: done
priority: P1
persona: Produksi
---

# Assert finished-goods stock on Work Order complete

## Problem

`FinishedGoodsHandler` exists under  
`app/Domain/Manufacturing/WorkOrders/Handlers/FinishedGoodsHandler.php`  
but was not wired into `WorkOrderService::complete()`, so production WO completion
only consumed raw materials — FG `ProductStock` never increased.

## Acceptance

- [x] Integration test: after `WorkOrderService::complete()`, FG product quantity increases by completed qty
- [x] Inventory movement TYPE_PRODUCTION for FG receipt
- [x] If accounting strategy posts WIP/FG JE, assert balanced lines (covered by manufacturing cost strategy wiring tests)
- [x] Test fails if handler disabled/skipped (`shouldHandle` without warehouse)

## Solution

`WorkOrderService::complete()` now calls `FinishedGoodsHandler` after material
consume + status transition when the WO is production with product + warehouse.

## Related code

- `app/Services/Manufacturing/WorkOrderService.php` (`complete`)
- `app/Domain/Manufacturing/WorkOrders/Handlers/FinishedGoodsHandler.php`
- `tests/Feature/Services/Manufacturing/WorkOrderFinishedGoodsReceiptTest.php`
- `tests/Feature/Integration/WorkflowIntegrationTest.php` (manufacturing describe)
