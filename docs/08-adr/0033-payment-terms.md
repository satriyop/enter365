---
adr: "0033"
title: "Payment Terms"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [payments, receivables, payables]
related_adrs: [0019, 0032]
related_modules: [accounting]
impact: medium
---

# ADR-0033: Payment Terms

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing invoice/bill due dates
- Working with payment terms setup
- Building credit management
- Understanding Indonesian payment customs

**Key takeaway:** Payment terms define due date calculation (Net 30, COD, etc.) and can include early payment discounts.

---

## Decision

Implement configurable payment terms with automatic due date calculation and optional early payment discounts.

---

## Context

Indonesian business payment practices:
1. Net 30 most common for B2B
2. COD (Cash on Delivery) for retail
3. Down payment (Uang Muka) often required
4. Early payment discounts sometimes offered

---

## Implementation

### PaymentTerm Model

```php
// payment_terms table
$table->string('code');                   // NET30, COD, 2/10NET30
$table->string('name');                   // "Net 30 Days"
$table->string('name_id');                // "Neto 30 Hari"
$table->integer('due_days');              // Days until due
$table->integer('discount_days')->nullable();    // Days for discount
$table->decimal('discount_percent', 5, 2)->nullable(); // Early discount %
$table->boolean('is_active')->default(true);
```

### Standard Payment Terms

| Code | Name | Due Days | Discount |
|------|------|----------|----------|
| COD | Cash on Delivery | 0 | - |
| NET7 | Net 7 Days | 7 | - |
| NET14 | Net 14 Days | 14 | - |
| NET30 | Net 30 Days | 30 | - |
| NET45 | Net 45 Days | 45 | - |
| NET60 | Net 60 Days | 60 | - |
| 2/10NET30 | 2% 10 Net 30 | 30 | 2% if paid within 10 days |
| EOM | End of Month | EOM | - |
| 15MFI | 15th of Following Month | 15 MFI | - |

### Due Date Calculation

```php
class PaymentTerm extends Model
{
    public function calculateDueDate(Carbon $invoiceDate): Carbon
    {
        return match ($this->code) {
            'COD' => $invoiceDate->copy(),
            'EOM' => $invoiceDate->copy()->endOfMonth(),
            '15MFI' => $invoiceDate->copy()->addMonth()->setDay(15),
            default => $invoiceDate->copy()->addDays($this->due_days),
        };
    }

    public function calculateDiscountDate(Carbon $invoiceDate): ?Carbon
    {
        if (!$this->discount_days) {
            return null;
        }

        return $invoiceDate->copy()->addDays($this->discount_days);
    }

    public function calculateDiscountAmount(int $amount): int
    {
        if (!$this->discount_percent) {
            return 0;
        }

        return (int) round($amount * ($this->discount_percent / 100));
    }
}
```

### Invoice Integration

```php
// When creating invoice
public function setPaymentTerms(PaymentTerm $term): void
{
    $this->payment_term_id = $term->id;
    $this->due_date = $term->calculateDueDate($this->date);
    $this->discount_date = $term->calculateDiscountDate($this->date);
    $this->discount_amount = $term->calculateDiscountAmount($this->total);
}
```

### Contact Default Terms

```php
// Contact model
$table->foreignId('default_payment_term_id')->nullable();

// When creating invoice for contact
$invoice->setPaymentTerms(
    $contact->defaultPaymentTerm ?? PaymentTerm::default()
);
```

### Early Payment Discount

```php
// 2/10 Net 30: 2% discount if paid within 10 days
public function recordPaymentWithDiscount(Invoice $invoice, int $amountPaid): Payment
{
    $discountTaken = 0;

    if ($invoice->discount_date &&
        now()->lte($invoice->discount_date) &&
        $amountPaid >= $invoice->total - $invoice->discount_amount) {
        $discountTaken = $invoice->discount_amount;
    }

    return Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => $amountPaid,
        'discount_taken' => $discountTaken,
    ]);
}

// Journal entry for discount
// DR Cash                    Rp 9,800,000
// DR Sales Discount          Rp   200,000
// CR Accounts Receivable     Rp 10,000,000
```

### Indonesian Terms

| English | Indonesian | Common Usage |
|---------|------------|--------------|
| Cash on Delivery | Tunai | Retail sales |
| Net 30 | Tempo 30 Hari | B2B standard |
| Down Payment | Uang Muka (UM) | Large purchases |
| Installment | Cicilan | Consumer goods |
| Early Payment Discount | Diskon Pelunasan Awal | Rare |

### Credit Limit Integration

```php
// Contact model
$table->bigInteger('credit_limit')->default(0);

// Check before creating invoice
public function canExtendCredit(Contact $contact, int $amount): bool
{
    $outstanding = $contact->invoices()
        ->where('status', '!=', 'paid')
        ->sum('amount_due');

    return ($outstanding + $amount) <= $contact->credit_limit;
}
```

---

## References

- [ADR-0019: Down Payment Application](./0019-down-payment-application.md)
- [ADR-0032: Aging Report Buckets](./0032-aging-report-buckets.md)
- [Sales Cycle](../02-domain/sales-cycle.md)

