---
status: done
priority: P0
persona: Architecture
---

# Perfect code isolation: electrical_panel + solar add-ons

## Boundaries

| Add-on | Services | Models | Provider | Flag |
|--------|----------|--------|----------|------|
| Electrical panel | `App\Services\ElectricalPanel` | `App\Models\ElectricalPanel` | `Addons\ElectricalPanelServiceProvider` | `electrical_panel` |
| Solar | `App\Services\Solar` | `App\Models\Solar` | `Addons\SolarServiceProvider` (conditional DI) | `solar_proposals` |

Core Manufacturing retains: Bom*, WorkOrder*, Mrp*, MaterialRequisition*, Subcontractor*.

## Residual (acceptable)

- Nullable FKs on BOM/template items for optional panel metadata
- Morph map alias for solar_proposal in AppServiceProvider
- Industry code remains in monorepo (not separate Composer packages)
