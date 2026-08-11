# Electrical Panel add-on (Vahana)

**Not part of core Manufacturing.** Core keeps BOM / WO / MR / MRP / Subcontracting only.

| Concern | Location |
|---------|----------|
| Services | `App\Services\ElectricalPanel` |
| Models | `App\Models\ElectricalPanel` |
| Flag | `features.modules.electrical_panel` |
| Provider | `App\Providers\Addons\ElectricalPanelServiceProvider` |
| Routes | `routes/api.php` middleware `feature:electrical_panel` (+ `feature:bom` for BOM-nested tools) |

Generic BOM templates may keep optional nullable FKs (`component_standard_id`, `spec_rule_set_id`) without requiring this package at runtime when the flag is off.
