# Odoo ↔ enter365 parity runner

Walks live Odoo (`enterkode.odoo.com`) and enter365 (`enter365.pamungkas.org`)
page-by-page from a **JourneyCatalog** of ordered **PagePair**s. Each pair is
captured in two Playwright contexts (dual-site). Output is paired screenshots
plus structured **DiffFinding** JSON — not a pixel diff.

Implements [satriyop/enter365#139](https://github.com/satriyop/enter365/issues/139).
Product gaps in #136–#138 are out of scope.

## Odoo is read-only always

The Odoo Playwright context **must not mutate** the live database.

Enforced in `lib/capture.mjs`:

- GET/HEAD/OPTIONS are allowed.
- Login POST (`/web/login`, `/web/session/authenticate`) is allowed so the
  session can start.
- JSON-RPC POSTs that only **read** (`web_search_read`, `get_views`, …) are
  allowed so list/form views can render.
- Writes are aborted: `create` / `write` / `unlink` / `web_save`,
  `/web/dataset/call_button`, `/web/action/run`, PUT/PATCH/DELETE, and known
  posting/confirm RPC methods.

Do not click Save, Confirm, Post, or any other mutating control on Odoo.
enter365 is a lab tenant and may be written.

## Environment

Never commit credentials. Pass them via the environment (or a local unsaved
shell). Defaults are the live walk hosts.

| Variable | Required for live run | Default |
|---|---|---|
| `ODOO_BASE_URL` | no | `https://enterkode.odoo.com` |
| `ODOO_EMAIL` | **yes** | — |
| `ODOO_PASSWORD` | **yes** | — |
| `ENTER365_BASE_URL` | no | `https://enter365.pamungkas.org` |
| `ENTER365_EMAIL` | **yes** | — |
| `ENTER365_PASSWORD` | **yes** | — |

Path templates (`{id}` on a pair) resolve from:

- `PARITY_<PAIR_ID>_ODOO_ID` / `PARITY_<PAIR_ID>_ENTER365_ID`
- or `PARITY_<PAIR_ID>_ID` for both

Example: quotation S00016 from the live walk uses database ids, not the
document number:

```bash
export PARITY_QUOTATION_DETAIL_ODOO_ID=16
export PARITY_QUOTATION_DETAIL_ENTER365_ID=1
```

## How to run

```bash
# Validate catalog and print the plan (no browsers, no credentials)
node scripts/parity/compare.mjs --dry-run

# First N pairs only
node scripts/parity/compare.mjs --dry-run --limit 3

# Live capture (needs credentials + Chromium)
npm install
npx playwright install chromium
export ODOO_EMAIL=...
export ODOO_PASSWORD=...
export ENTER365_EMAIL=...
export ENTER365_PASSWORD=...
node scripts/parity/compare.mjs
node scripts/parity/compare.mjs --limit 1 --headed
```

If browsers or credentials are missing the runner exits non-zero, names the
**variable** that is missing (never the value), and does not write secrets.

Artifacts land in `storage/parity-runs/<run-id>/` (gitignored):

```
<pair-id>-odoo.png
<pair-id>-enter365.png
<pair-id>.json          # CaptureResult + DiffFinding[]
report.json             # per-journey summary
```

## PagePair fields

| Field | Notes |
|---|---|
| `id` | Stable kebab-case id. First pair **must** be `post-login`. |
| `journey` | Grouping key (`home`, `accounting`, `sales`, …). |
| `title` | Human label. |
| `odoo.path` | Path on `ODOO_BASE_URL`. `{id}` templates are ok. |
| `enter365.path` | Path on `ENTER365_BASE_URL`. Prefer real SPA routes from this repo. |
| `compare[]` | One or more of `chrome`, `filters`, `columns`, `badges`, `actions`, `status_workflow`, `labels`. |
| `role` | Optional persona hint (`accountant`, `sales`, …). |

### Adding a PagePair

1. Append a mapping under `pairs:` in `catalog.yaml`. Order is the walk order.
2. Use a real enter365 SPA path (see `tests/Browser/SmokeTest.php`).
3. Prefer Odoo 18+ readable `/odoo/...` URLs; hash `#action=` URLs are fine
   when a readable path is unknown.
4. List only the dimensions you care about in `compare`.
5. Re-run `node scripts/parity/compare.mjs --dry-run` before a live capture.

```yaml
  - id: journal-entries
    journey: accounting
    title: Journal entries list
    odoo.path: /odoo/accounting/account.move
    enter365.path: /accounting/journal-entries
    compare:
      - chrome
      - filters
      - columns
      - actions
    role: accountant
```

## DiffFinding

Each compared dimension becomes one finding:

| `severity` | Meaning |
|---|---|
| `match` | Enough overlap between Odoo (expected) and enter365 (actual). |
| `mismatch` | Both sides showed the dimension, but labels/actions/columns differ. |
| `gap` | Odoo showed something enter365 did not (or `{id}` was unresolved). |

`expected` is always the Odoo observation; `actual` is enter365. `evidence`
points at the two screenshots for that pair.

This is a **capture + structured note** tool. It does not close product parity
gaps (#136–#138).
