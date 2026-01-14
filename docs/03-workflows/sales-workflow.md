---
workflow: sales
title: "Sales Workflow"
entities: [Quotation, Invoice, Payment, DeliveryOrder]
services: [QuotationService, InvoiceService, PaymentService]
tags: [sales, receivables]
---

# Sales Workflow

## AI Agent Quick Reference

**Use this workflow when:**
- Implementing sales features
- Understanding quotation-to-cash flow
- Debugging sales process issues
- Building sales reports

**Key flow:** Quotation → (Approve) → Invoice → Delivery → Payment

---

## Complete Sales Flow

```
                                    ┌─────────────────┐
                                    │   Customer      │
                                    │   Inquiry       │
                                    └────────┬────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           QUOTATION PHASE                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Create     │───▶│   Send to   │───▶│  Customer   │                 │
│   │  Quotation  │    │  Customer   │    │  Decision   │                 │
│   │  (draft)    │    │  (sent)     │    │             │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│                           ┌─────────────────────┼─────────────────────┐  │
│                           ▼                     ▼                     ▼  │
│                    ┌─────────────┐       ┌─────────────┐      ┌────────┐│
│                    │  Accepted   │       │  Revision   │      │Rejected││
│                    │             │       │  Requested  │      │        ││
│                    └──────┬──────┘       └─────────────┘      └────────┘│
│                           │                                              │
└───────────────────────────┼──────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           INVOICING PHASE                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐         ┌─────────────┐         ┌─────────────┐       │
│   │  Convert    │────────▶│  Approve    │────────▶│   Send      │       │
│   │  to Invoice │         │  Invoice    │         │   Invoice   │       │
│   │  (draft)    │         │  (approved) │         │   (sent)    │       │
│   └─────────────┘         └─────────────┘         └──────┬──────┘       │
│                                                          │               │
│         ┌────────────────────────────────────────────────┘               │
│         │                                                                │
│         ▼                                                                │
│   ┌─────────────┐                                                        │
│   │   Down      │ (Optional - if DP required)                           │
│   │   Payment   │                                                        │
│   └──────┬──────┘                                                        │
│          │                                                               │
└──────────┼───────────────────────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           DELIVERY PHASE                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐         ┌─────────────┐         ┌─────────────┐       │
│   │  Create     │────────▶│  Pick &     │────────▶│  Deliver    │       │
│   │  Surat Jalan│         │  Pack       │         │  to Customer│       │
│   │  (draft)    │         │             │         │  (delivered)│       │
│   └─────────────┘         └─────────────┘         └──────┬──────┘       │
│                                                          │               │
│                                    {Update inventory}◀───┘               │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           PAYMENT PHASE                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐         ┌─────────────┐         ┌─────────────┐       │
│   │  Receive    │────────▶│  Apply to   │────────▶│  Invoice    │       │
│   │  Payment    │         │  Invoice    │         │  Paid       │       │
│   │             │         │             │         │  (closed)   │       │
│   └─────────────┘         └─────────────┘         └─────────────┘       │
│                                                                          │
│         │                                                                │
│         ▼                                                                │
│   {Create Journal Entry}                                                 │
│   DR Cash/Bank                                                           │
│   CR Accounts Receivable                                                 │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Status Transitions

### Quotation Statuses

```
draft ──▶ sent ──▶ accepted ──▶ converted
                └──▶ rejected
                └──▶ expired
```

### Invoice Statuses

```
draft ──▶ approved ──▶ sent ──▶ partial ──▶ paid
                            └──▶ overdue
     └──▶ cancelled
```

---

## Key Decision Points

| Point | Question | Actions |
|-------|----------|---------|
| Quotation sent | Customer accept? | Accept → Invoice, Reject → End, Revise → New version |
| Invoice approved | Need DP? | Yes → Collect DP first, No → Proceed |
| Delivery ready | Stock available? | Yes → Deliver, No → Wait/partial |
| Payment received | Full amount? | Yes → Close, No → Partial payment |

---

## Automation Triggers

| Event | System Action |
|-------|---------------|
| Quotation accepted | Create Invoice (draft) |
| Invoice approved | Update AR balance |
| Delivery confirmed | Decrement inventory |
| Payment received | Create journal entry |
| Invoice overdue | Send reminder notification |

---

## Related Documents

- [ADR-0015: Multi-Option Quotations](../08-adr/0015-multi-option-quotations.md)
- [ADR-0019: Down Payment Application](../08-adr/0019-down-payment-application.md)
- [ADR-0033: Payment Terms](../08-adr/0033-payment-terms.md)
- [Sales Cycle Domain](../02-domain/sales-cycle.md)

