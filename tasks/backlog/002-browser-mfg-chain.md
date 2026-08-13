---
status: done
priority: P1
persona: Produksi
depends_on: 001-assert-fg-stock-on-wo-complete
---

# Browser E2E: manufacturing chain

## Problem

Zero files under `tests/Browser/` for BOM / Work Order / MR / MRP.  
FE pages and `useWorkOrders` / `useMrp` / `useBoms` already call real APIs.

## Acceptance

- [x] Browser test creates/uses BOM (or seeded BOM)
- [x] Create WO from BOM via UI or documented hybrid (API `POST /boms/{id}/create-work-order`)
- [x] MR issue path exercised; raw stock ↓ on MR issue (no double-deduct on WO complete)
- [x] Complete WO; FG stock assert (after 001)
- [x] No reliance on SmokeTest-style “page loads only”

## Related

- `tests/Browser/ManufacturingChainTest.php`
- `front-end-enter365/src/pages/work-orders/*`
- `front-end-enter365/src/pages/manufacturing/material-requisitions/*`
- `front-end-enter365/src/api/useWorkOrders.ts`

## FE fixes required for chain

- Approve button: API status is `draft` (was checking `pending` only)
- Issue payload: API expects `items.*.quantity` (was sending `quantity_issued`)
