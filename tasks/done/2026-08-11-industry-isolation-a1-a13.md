---
status: done
priority: P0
persona: Product
---

# Industry isolation A1–A13

## Done

- FE: BOM brand-swap / cost-opt / template brand UI gated on `electrical_panel`
- FE: project list + dashboard pack labels de-NEX’d
- BE: SpecValidation + BomTemplateService soft-skip when panel off
- BE: BomItem / BomTemplateItem resources omit industry fields when off
- Seed: component standards / library only with electrical_panel; solar/PLN only with solar_proposals
- Demo: `enterprise` profile; manufacturing preset no longer maps to Vahana
- A10: BrandSwap README — isolation via gates, no namespace move
- Tests: `tests/Feature/IndustryIsolationTest.php` + FeatureFlagsTest

## Commits

- BE `b9baa5e` feat: isolate industry add-ons from enterprise core (A1–A13 BE)
- FE `638e0c5` feat: gate panel brand UI and neutralize solar project copy
