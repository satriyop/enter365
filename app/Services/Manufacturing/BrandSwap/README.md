# Brand swap services (electrical_panel add-on)

These classes implement Vahana electrical-panel brand swap, preview, and related
helpers. They live under `Manufacturing/` for historical package layout.

**Product isolation (A10 bar):**

- HTTP routes are gated by `feature:bom` + `feature:electrical_panel`
- `SpecValidationService` soft-skips when `electrical_panel` is off
- SPA BOM detail / template wizard hide brand UI when the flag is off

A full namespace move to `App\Services\ElectricalPanel\` is deferred; isolation is
enforced at the route/service-guard/UI layer rather than a folder rename.
