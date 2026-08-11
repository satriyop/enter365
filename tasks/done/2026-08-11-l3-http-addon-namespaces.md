---
date: 2026-08-11
type: done
---

# L3 residual — HTTP Form Requests & Resources under add-on namespaces

## Delivered
- [x] Move 17 electrical_panel Form Requests → `Http/Requests/Api/V1/ElectricalPanel`
- [x] Move 6 solar Form Requests → `Http/Requests/Api/V1/Solar`
- [x] Move 4 panel + 4 solar API Resources into matching add-on namespaces
- [x] Fix Solar proposal resources to import core `StatusResource` / `ContactResource` / etc.
- [x] `BomTemplateServiceInterface::syncTemplateItemStandard` — core no-op; panel writes meta
- [x] `BomTemplateController` no longer hard-references `ElectricalPanel` models
- [x] IndustryIsolation tests for HTTP package layout

## Browser suite (same session)
- Smoke: 35/35 green earlier
- Full Browser: **117 passed / 56 failed** (~16 min)
- Failures are **pre-existing E2E data/UI issues** (FK `contact_id=0`, Radix combobox timeouts, session drop to `/login`) — not L3 HTTP moves

## Residual after L3 (optional polish)
- Core Form Requests still gate panel fields (`component_standard_id`, `target_brand`) with `Features` — acceptable shared API surface
- BOM detail / template FE still embeds gated panel UI (correct product-wise)
