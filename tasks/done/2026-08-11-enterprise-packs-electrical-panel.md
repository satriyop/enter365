---
status: done
priority: P0
persona: Product
---

# Enterprise packs + electrical_panel extract (A+B)

## Goal

Align product model with Odoo Enterprise–like packaging:

- Manufacturing / Projects = **odoo packs** (not industry verticals)
- **NEX** = `solar_proposals` add-on
- **Vahana** = `electrical_panel` add-on (brand swap, standards, spec validation)

## Done

- [x] `config/features.php` — presets: `general`, `services`, `manufacturing`, `enterprise`, `solar`/`nex`, `vahana`, `full`
- [x] New module flag `electrical_panel` (`FEATURE_ELECTRICAL_PANEL`)
- [x] BE: BrandSwap / ComponentStandard / SpecValidation / cost-opt routes nested under `feature:bom` + `feature:electrical_panel`
- [x] Generic BOM + bom-templates remain on `feature:bom` only
- [x] FE: sidebar + router gates for component-library, rule-sets, cost-optimization → `electrical_panel`
- [x] `.env.example` docs
- [x] Tests: FeatureFlagsTest product policy + middleware for electrical_panel
- [x] Mapping artifact updated

## Env cheat sheet

```bash
FEATURE_PRESET=enterprise   # Odoo-like pitch
FEATURE_PRESET=vahana       # MFG + electrical_panel
FEATURE_PRESET=nex          # alias of solar
FEATURE_ELECTRICAL_PANEL=true  # override
```
