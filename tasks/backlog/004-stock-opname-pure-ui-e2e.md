---
status: open
priority: P2
persona: Gudang
---

# Stock opname: pure UI browser E2E

## Problem

`tests/Browser/StockOpnameTest.php` uses API for steps that hit native  
`confirm()` / `prompt()` (Playwright auto-dismisses). Business logic is tested  
in service/integration; SPA path is not fully proven.

## Acceptance

- [ ] Replace browser dialogs with in-app modals (or Playwright dialog handlers)
- [ ] Browser test runs create → count → review → approve without direct API for core steps
- [ ] DB assert stock variance applied on approve

## Related

- `tests/Browser/StockOpnameTest.php` (NOTE at top of file)
- Stock opname detail page in FE
