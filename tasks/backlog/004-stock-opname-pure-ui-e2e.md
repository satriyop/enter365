---
status: done
priority: P2
persona: Gudang
---

# Stock opname: pure UI browser E2E

## Problem

`tests/Browser/StockOpnameTest.php` used API for steps that hit native  
`confirm()` / `prompt()` (Playwright auto-dismisses). Business logic is tested  
in service/integration; SPA path was not fully proven.

## Acceptance

- [x] Replace browser dialogs with in-app modals (generate, approve, reject, cancel, delete item, list delete)
- [x] Browser test runs create → count → review → approve without direct API for core steps
- [x] DB assert stock variance applied on approve

## Related

- `tests/Browser/StockOpnameTest.php`
- `front-end-enter365/src/pages/inventory/StockOpnameDetailPage.vue`
- `front-end-enter365/src/pages/inventory/StockOpnameListPage.vue`
- `app/Http/Resources/Api/V1/StockOpnameItemResource.php` (null actual_quantity when uncounted)

## Fixes required for chain

- Native `confirm`/`prompt` → Modal
- API cast `(float) null` made uncounted items look like qty 0; return `null` for uncounted
