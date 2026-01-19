# Accounting Workflow

> **Fiscal Period Management and Year-End Close Process**
>
> This document covers the complete accounting workflow from daily operations through year-end closing.

---

## Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    FISCAL PERIOD LIFECYCLE                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│   ┌──────┐      ┌────────┐      ┌─────────┐      ┌────────┐         │
│   │ Open │ ───► │ Locked │ ───► │ Closing │ ───► │ Closed │         │
│   └──────┘      └────────┘      └─────────┘      └────────┘         │
│       ▲             │               │                │               │
│       │             │               │                │               │
│       └─────────────┴───────────────┴────────────────┘               │
│                         (reopen)                                      │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Daily Operations (Period: Open)

### Allowed Activities

| Activity | Auto Journal? | Description |
|----------|:-------------:|-------------|
| Post Invoices | ✅ Yes | Creates AR/Revenue entries |
| Post Bills | ✅ Yes | Creates AP/Expense entries |
| Record Payments | ✅ Yes | Creates Bank/AR or AP entries |
| Manual Journal Entries | - | Direct journal posting |
| Inventory Movements | ✅ Yes | Creates COGS/Inventory entries |

### Journal Entry Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Draft     │ ──► │   Posted    │ ──► │  Reversed   │
│  (Editable) │     │ (Permanent) │     │  (If error) │
└─────────────┘     └─────────────┘     └─────────────┘
```

**Key Rules:**
- Draft entries can be edited/deleted
- Posted entries are permanent
- Reversals create new offsetting entries

---

## Period Lock (Period: Locked)

### Purpose

Lock a period to prevent accidental modifications while preparing for close.

### Trigger

```
POST /api/v1/fiscal-periods/{id}/lock
```

### What Changes

| Activity | Allowed? |
|----------|:--------:|
| New transactions | ❌ No |
| Post existing drafts | ❌ No |
| View reports | ✅ Yes |
| Unlock period | ✅ Yes |

### State Machine Transition

```php
// In FiscalPeriodStateMachine
$stateMachine->lock(); // Open → Locked
$stateMachine->unlock(); // Locked → Open
```

---

## Year-End Close Process

### Pre-Close Checklist

Before closing, the system validates:

```
GET /api/v1/fiscal-periods/{id}/closing-checklist
```

| Check | Status | Blocking? |
|-------|--------|:---------:|
| Unposted Journals | Must be 0 | ✅ Yes |
| Trial Balance | Must balance | ✅ Yes |
| Required Accounts | Must exist | ✅ Yes |
| Draft Documents | Warning only | ⚠️ No |

**Response Example:**
```json
{
  "can_close": true,
  "items": {
    "unposted_journals": {
      "status": "ok",
      "count": 0,
      "message": "Semua jurnal sudah diposting"
    },
    "trial_balance": {
      "status": "ok",
      "count": 0,
      "message": "Neraca saldo seimbang"
    },
    "required_accounts": {
      "status": "ok",
      "count": 0,
      "message": "Semua akun yang diperlukan tersedia"
    }
  },
  "summary": "Siap untuk ditutup"
}
```

### Close Execution

```
POST /api/v1/fiscal-periods/{id}/close
```

**Options:**
```json
{
  "skip_next_period": false,
  "skip_opening_balances": false,
  "notes": "Year-end close 2025"
}
```

### 7-Step Closing Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    YEAR-END CLOSE STEPS                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Step 1: Lock Period                                                  │
│     └─► Prevents new transactions                                     │
│                                                                       │
│  Step 2: Validate Checklist                                           │
│     └─► Ensures all prerequisites met                                 │
│                                                                       │
│  Step 3: Close Temporary Accounts ⚡                                  │
│     └─► Creates closing entry:                                        │
│         - Debit all Revenue accounts (zero them)                      │
│         - Credit all Expense accounts (zero them)                     │
│         - Net to Retained Earnings                                    │
│                                                                       │
│  Step 4: Close Dividends (if applicable) ⚡                           │
│     └─► Transfer Dividends/Withdrawals to Retained Earnings           │
│                                                                       │
│  Step 5: Mark Period Closed                                           │
│     └─► Updates status, sets closed_at timestamp                      │
│                                                                       │
│  Step 6: Create Next Period (optional)                                │
│     └─► Auto-creates next fiscal year                                 │
│                                                                       │
│  Step 7: Populate Opening Balances (optional) ⚡                      │
│     └─► Creates opening balance entries in new period                 │
│                                                                       │
│  ⚡ = Creates journal entry (reversible)                              │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Closing Entry Example

**Scenario:** Period has Rp 100M revenue, Rp 60M expenses

```
┌─────────────────────────────────────────────────────────────────────┐
│ CLOSING ENTRY - December 31, 2025                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│ Debit:  4-1001 Pendapatan Penjualan      Rp 100,000,000              │
│ Credit: 5-1001 Harga Pokok Penjualan                  Rp 60,000,000  │
│ Credit: 3-2000 Laba Ditahan                           Rp 40,000,000  │
│                                                                       │
│ Description: Jurnal penutup periode Tahun Fiskal 2025                │
│ Reference: CLOSE-{period_id}                                          │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

**Result:**
- All Revenue accounts = 0
- All Expense accounts = 0
- Retained Earnings increased by Net Income (Rp 40M)

---

## Closing Strategies

Two strategies available (configurable in `config/accounting.php`):

### 1. Direct Closing (Default)

Revenue and Expense close **directly** to Retained Earnings.

```
Revenue ───────────────────────► Retained Earnings
Expense ───────────────────────► Retained Earnings
```

**Best for:** Small businesses, simpler accounting

### 2. Income Summary Closing

Revenue and Expense close to **Income Summary** first, then to Retained Earnings.

```
Revenue ───► Income Summary ───► Retained Earnings
Expense ───► Income Summary ───►
```

**Best for:** Larger businesses wanting clearer audit trail

**Configuration:**
```php
// config/accounting.php
'policies' => [
    'closing_strategy' => 'direct', // or 'income_summary'
],
```

---

## Progress Tracking

The closing process returns progress information:

```json
{
  "success": true,
  "message": "Periode berhasil ditutup",
  "progress": {
    "is_complete": true,
    "percent_complete": 100,
    "completed_steps": [
      "lock_period",
      "validate_checklist",
      "close_temporary_accounts",
      "mark_period_closed"
    ],
    "journal_entry_ids": [1234, 1235]
  },
  "next_period": {
    "id": 2,
    "name": "Tahun Fiskal 2026",
    "start_date": "2026-01-01"
  }
}
```

---

## Rollback / Reopen

### Reopen a Closed Period

```
POST /api/v1/fiscal-periods/{id}/reopen
```

**What happens:**
1. Reverses closing journal entries
2. Sets status back to Open
3. Clears closed_at timestamp

**Restrictions:**
- Cannot reopen if next period has transactions
- Requires admin permission

### Rollback Partial Close

If closing fails mid-process, the system tracks which journal entries were created and can reverse them:

```php
// YearEndCloseService
$service->rollbackClose($period, $progress);
```

---

## Domain Events

Events fired during the closing workflow:

| Event | When Fired |
|-------|------------|
| `FiscalPeriodLocked` | Period locked |
| `FiscalPeriodClosing` | Close process started |
| `FiscalPeriodClosed` | Close completed successfully |
| `FiscalPeriodReopened` | Closed period reopened |
| `FiscalPeriodStatusChanged` | Any status change |

**Event Payload:**
```php
class FiscalPeriodClosed
{
    public FiscalPeriod $fiscalPeriod;
    public int $netIncome;
    public ?int $closingEntryId;
    public ?User $closedBy;
}
```

---

## Related Code

| Component | Path |
|-----------|------|
| State Machine | `app/Domain/Accounting/FiscalPeriods/FiscalPeriodStateMachine.php` |
| Orchestrator | `app/Services/Accounting/YearEndCloseService.php` |
| Strategies | `app/Services/Accounting/Strategies/Closing/` |
| Events | `app/Domain/Accounting/FiscalPeriods/Events/` |
| Value Objects | `app/Domain/Accounting/FiscalPeriods/ValueObjects/` |
| Enums | `app/Domain/Accounting/FiscalPeriods/Enums/` |

---

## Related Documentation

- [Business Rules - Fiscal Year](/docs/06-business-rules/README.md#fiscal-year)
- [State Machine Pattern](/docs/07-code-patterns/state-machine-pattern.md)
- [Strategy Pattern](/docs/07-code-patterns/strategy-pattern.md)
- [Domain Events Pattern](/docs/07-code-patterns/event-listener-pattern.md)
- [ADR-0044: Fiscal Period Lock](/docs/08-adr/0044-fiscal-period-lock.md)
