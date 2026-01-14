---
adr: "0043"
title: "Bank Reconciliation"
status: accepted
date: 2024-11-15
deciders: [Product Team, Accounting Advisor]
tags: [accounting, banking]
related_adrs: [0011]
related_modules: [accounting]
impact: medium
---

# ADR-0043: Bank Reconciliation

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing bank statement import
- Building reconciliation matching
- Handling unmatched transactions
- Creating reconciliation reports

**Key takeaway:** Match bank statement transactions to system transactions for balance verification.

---

## Decision

Implement bank reconciliation with statement import, automatic matching, and variance tracking.

---

## Context

Bank reconciliation needed for:
1. Balance verification
2. Fraud detection
3. Missing transaction identification
4. Month-end closing

---

## Implementation

### Bank Account Model

```php
// bank_accounts table
$table->string('name');                  // BCA - Operational
$table->string('account_number');        // 1234567890
$table->string('bank_name');             // BCA
$table->foreignId('ledger_account_id');  // Linked COA account
$table->bigInteger('current_balance')->default(0);
$table->date('last_reconciled_date')->nullable();
```

### Bank Statement Model

```php
// bank_statements table
$table->foreignId('bank_account_id');
$table->date('statement_date');
$table->bigInteger('opening_balance');
$table->bigInteger('closing_balance');
$table->string('status');                // pending, reconciled
```

### Bank Transaction Model

```php
// bank_transactions table
$table->foreignId('bank_statement_id');
$table->date('transaction_date');
$table->string('description');
$table->string('reference')->nullable();
$table->bigInteger('debit')->default(0);
$table->bigInteger('credit')->default(0);
$table->string('match_status');          // unmatched, matched, manual
$table->morphs('matched_transaction')->nullable();
```

### Reconciliation Process

```
Import Statement → Auto-Match → Manual Review → Reconcile
       │               │              │            │
       ▼               ▼              ▼            ▼
   Parse CSV      Match by        Match         Mark
   Create rows    reference     remaining     reconciled
```

### Auto-Matching Service

```php
class BankReconciliationService
{
    public function autoMatch(BankStatement $statement): int
    {
        $matched = 0;

        foreach ($statement->transactions as $txn) {
            // Skip already matched
            if ($txn->match_status !== 'unmatched') continue;

            // Try match by reference
            $match = $this->findMatchByReference($txn);

            // Try match by amount and date
            if (!$match) {
                $match = $this->findMatchByAmountDate($txn);
            }

            if ($match) {
                $txn->update([
                    'match_status' => 'matched',
                    'matched_transaction_type' => get_class($match),
                    'matched_transaction_id' => $match->id,
                ]);
                $matched++;
            }
        }

        return $matched;
    }

    private function findMatchByReference(BankTransaction $txn): ?Model
    {
        // Check payments
        $payment = Payment::where('reference', $txn->reference)
            ->where('amount', $txn->credit ?: $txn->debit)
            ->first();

        if ($payment) return $payment;

        // Check receipts
        return Receipt::where('reference', $txn->reference)
            ->where('amount', $txn->credit ?: $txn->debit)
            ->first();
    }

    private function findMatchByAmountDate(BankTransaction $txn): ?Model
    {
        $amount = $txn->credit ?: $txn->debit;
        $dateRange = [$txn->transaction_date->subDays(3), $txn->transaction_date->addDays(3)];

        // Find unreconciled payment/receipt with same amount
        return Payment::whereBetween('date', $dateRange)
            ->where('amount', $amount)
            ->whereNull('reconciled_at')
            ->first();
    }
}
```

### Reconciliation Status

| Status | Bank | System | Action |
|--------|------|--------|--------|
| Matched | ✓ | ✓ | None |
| Bank only | ✓ | ✗ | Create transaction |
| System only | ✗ | ✓ | Void or wait |
| Amount differs | ✓ | ✓ | Investigate |

### CSV Import

```php
public function importStatement(BankAccount $account, UploadedFile $file): BankStatement
{
    $statement = BankStatement::create([
        'bank_account_id' => $account->id,
        'statement_date' => now(),
        'status' => 'pending',
    ]);

    $csv = Reader::createFromPath($file->path());
    $csv->setHeaderOffset(0);

    foreach ($csv->getRecords() as $record) {
        BankTransaction::create([
            'bank_statement_id' => $statement->id,
            'transaction_date' => Carbon::parse($record['date']),
            'description' => $record['description'],
            'reference' => $record['reference'] ?? null,
            'debit' => $this->parseAmount($record['debit'] ?? 0),
            'credit' => $this->parseAmount($record['credit'] ?? 0),
            'match_status' => 'unmatched',
        ]);
    }

    return $statement;
}
```

### Reconciliation Report

```
Bank Reconciliation - BCA Operational
As of: 31 January 2024

System Balance:                    Rp 150,000,000
Add: Deposits in Transit           Rp   5,000,000
Less: Outstanding Checks           Rp  (3,000,000)
                                   ----------------
Adjusted System Balance:           Rp 152,000,000

Bank Statement Balance:            Rp 152,000,000
                                   ================
Difference:                        Rp           0
```

---

## References

- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
- [Accounting Domain](../02-domain/accounting.md)

