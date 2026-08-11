---
date: 2026-08-11
type: done
---

# Fase A — panel extension schema fully off core BOM tables

## Goal
100% lepas step: electrical_panel FKs no longer live on core manufacturing tables.

## Delivered
- [x] Meta tables `electrical_panel_bom_*_meta` + data copy + drop core columns
- [x] `Bom*PanelMeta` models + `resolveRelationUsing` on core BOM models
- [x] Core `BomTemplateService` product/manual only; brand resolver deleted
- [x] Panel `BomTemplateService` bound when `electrical_panel` on
- [x] Resources read `component_standard_id` from `panelMeta`
- [x] Pest helpers `attachBomItemStandard` / `attachTemplateItemStandard`
- [x] Tests green (BrandSwap, BomTemplate, ComponentStandard, IndustryIsolation)

## Commits
- `702b4cc` extract FKs to meta tables
- `3e77d3e` meta models + relation macros
- `f35ec50` brand-aware templates only in ElectricalPanel
- `4144d4b` test helpers + suite updates
