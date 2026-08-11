---
status: open
type: artifact
---

# Test quality criteria (Enter365)

Use when deciding Go / Soft / No-Go.

## Strong (counts for go-live)

Must assert **business side effects**, not only HTTP 200 / “see text”:

| Domain | Required evidence |
|--------|-------------------|
| Posting financial docs | `journal_entries` posted; line debits = credits; key accounts (AR/AP/Cash/Revenue) |
| Payments | `paid_amount` on invoice/bill; void reverses JE + amount |
| Inventory out/in | `product_stocks.quantity` delta; `inventory_movements` type/qty |
| Trial balance | Sum debit = sum credit over posted lines |
| Status workflow | Status via state machine path, not raw DB status set in test as “success” |

Prefer: service/integration **without** mocking `InventoryService` / `JournalService` for path under test.  
Mocks OK only for unit isolation of **call shape**, not as sole proof of stock/JE.

## Weak (does not unlock Go)

- Mock inventory/journal only
- `assertSee` / `assertNoJavascriptErrors` alone (`SmokeTest`)
- Hybrid: seed/update rows then only paint UI
- Pipeline unit tests with mock handlers
- Stats/CRUD without side-effect asserts

## Browser tests

- Use `realDb()` / `browser_pgsql` pattern already in suite
- Wait for backend status before UI assert (existing helpers)
- SPA must be running for browser suite; document prerequisite in test header
