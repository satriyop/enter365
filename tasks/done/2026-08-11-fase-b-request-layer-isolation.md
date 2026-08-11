---
date: 2026-08-11
type: done
---

# Fase B — HTTP request layer isolation + template duplicate meta

## Goal
After schema split (Fase A), stop core Form Requests / duplicate path from accepting or losing panel fields.

## Delivered
- [x] `component_standard_id` / `default_rule_set_id` / `target_brand` **prohibited** when `electrical_panel` off
- [x] `CreateBomFromTemplateRequest` no longer always imports panel brands (gated)
- [x] `duplicateTemplate` on `BomTemplateServiceInterface` — core copies lines; panel copies meta
- [x] Controller delegates duplicate to service
- [x] Tests: isolation request gates + duplicate preserves component standards

## Follow-ups (Fase C candidates)
- FE types regen if OpenAPI shape drifted
- Move remaining panel-only Form Requests under `Http/Requests/.../ElectricalPanel` namespaces (optional package hygiene)
- Thin controller: route storeItem standard sync fully through panel service method
