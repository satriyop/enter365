---
status: open
priority: P1
persona: Produksi
depends_on: 001-assert-fg-stock-on-wo-complete
---

# Browser E2E: manufacturing chain

## Problem

Zero files under `tests/Browser/` for BOM / Work Order / MR / MRP.  
FE pages and `useWorkOrders` / `useMrp` / `useBoms` already call real APIs.

## Acceptance

- [ ] Browser test creates/uses BOM (or seeded BOM)
- [ ] Create WO from BOM via UI or documented hybrid
- [ ] MR issue reduces stock (DB assert)
- [ ] Complete WO; FG stock assert (after 001)
- [ ] No reliance on SmokeTest-style “page loads only”

## Related

- `front-end-enter365/src/pages/work-orders/*`
- `front-end-enter365/src/pages/boms/*`
- `front-end-enter365/src/api/useWorkOrders.ts`
