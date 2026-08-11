# Solar EPC add-on (NEX)

**Not part of core Sales / Accounting.**

| Concern | Location |
|---------|----------|
| Services | `App\Services\Solar` |
| Models | `App\Models\Solar` |
| Flag | `features.modules.solar_proposals` |
| Provider | `App\Providers\Addons\SolarServiceProvider` (bindings only when flag ON) |
| Routes | `feature:solar_proposals` on public + auth solar routes |

Foundation seeders skip PLN / irradiance masters when the flag is off.
