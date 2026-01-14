---
adr: "0019"
title: "Down Payment Application"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [receivables, domain]
related_adrs: [0018]
related_modules: [sales]
impact: medium
---

# ADR-0019: Down Payment Application

## AI Agent Quick Reference

**Use this ADR when:**
- Working with down payments (uang muka)
- Applying DP to invoices
- Understanding DP accounting
- Building DP reports

**Key takeaway:** Down payments are tracked separately and applied to invoices when created.

---

## Decision

Down payments are recorded as separate documents and applied to invoices, reducing the invoice balance.

---

## Context

Indonesian business practice:
- **Uang Muka (UM)**: 30-50% paid before work begins
- **Progress payments**: Paid at milestones
- **Final payment**: On delivery/completion

---

## Implementation

### DownPayment Model

```php
$table->string('down_payment_number');      // DP-202401-0001
$table->foreignId('contact_id');
$table->bigInteger('amount');               // Total DP received
$table->bigInteger('applied_amount');       // Amount used on invoices
$table->bigInteger('remaining_amount');     // Available to apply
$table->string('status');                   // received, partial, applied
```

### Journal Entry (on Receipt)

```
DR Cash/Bank                  Rp 30,000,000
CR Down Payment Received      Rp 30,000,000
   (Liability until applied)
```

### Application to Invoice

```php
public function applyToInvoice(
    DownPayment $dp,
    Invoice $invoice,
    int $amount
): DownPaymentApplication {
    if ($amount > $dp->remaining_amount) {
        throw new Exception('Jumlah melebihi sisa uang muka.');
    }

    return DB::transaction(function () use ($dp, $invoice, $amount) {
        // Create application record
        $application = DownPaymentApplication::create([
            'down_payment_id' => $dp->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);

        // Update DP
        $dp->increment('applied_amount', $amount);
        $dp->decrement('remaining_amount', $amount);

        // Update invoice balance
        $invoice->decrement('balance', $amount);

        // Create journal entry
        $this->createApplicationJournal($application);

        return $application;
    });
}
```

### Application Journal Entry

```
DR Down Payment Received      Rp 30,000,000
CR Accounts Receivable        Rp 30,000,000
   (Reduces AR by DP amount)
```

### Reporting

```php
// Outstanding down payments
DownPayment::where('remaining_amount', '>', 0)->get();

// DP aging
DownPayment::where('remaining_amount', '>', 0)
    ->where('created_at', '<', now()->subDays(90))
    ->get();
```

---

## References

- [ADR-0018: Subcontractor Retention](./0018-subcontractor-retention.md)
- [Sales Cycle](../02-domain/sales-cycle.md)
