---
adr: "0025"
title: "Solar Proposal System"
status: accepted
date: 2024-11-15
deciders: [Product Team, Domain Expert]
tags: [solar, domain, killer-feature]
related_adrs: [0015, 0023]
related_modules: [solar]
impact: high
---

# ADR-0025: Solar Proposal System

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing solar features
- Understanding ROI calculations
- Working with PLN tariffs
- Building the customer portal

**Key takeaway:** Solar proposals auto-calculate system sizing, ROI, and payback based on customer's electricity usage.

---

## Decision

Implement automated solar proposal system with ROI calculations, PLN tariff integration, and customer self-service portal.

---

## Context

Solar EPC contractors need:
1. Quick system sizing from consumption data
2. Accurate ROI and payback calculations
3. Professional proposals for customers
4. Customer portal for proposal review

---

## Implementation

### Key Calculations

**System Sizing:**
```php
$dailyKwh = $monthlyConsumption / 30;
$peakSunHours = $irradiance; // 4.5-5.5 for Indonesia
$efficiency = 0.80;

$systemSizeKwp = $dailyKwh / $peakSunHours / $efficiency;
```

**Annual Production:**
```php
$annualKwh = $systemSizeKwp * $peakSunHours * 365 * $efficiency;
```

**Financial Analysis:**
```php
$annualSavings = $annualKwh * $tariffPerKwh;
$paybackYears = $systemCost / $annualSavings;
$lifetimeRoi = (($annualSavings * 25) - $systemCost) / $systemCost * 100;
```

### Data Sources

| Data | Source | Purpose |
|------|--------|---------|
| PLN Tariffs | `pln_tariffs` table | Electricity rates |
| Solar Irradiance | `indonesia_solar_data` | Regional sun hours |
| Panel Specs | Product catalog | System design |

### Customer Portal

Public link: `/solar/{public_token}`

Features:
- View full proposal
- See financial projections
- Accept/reject
- Download PDF

### Workflow

```
Site Assessment → Auto-Calculate → Generate Proposal
                        │                 │
                        ▼                 ▼
                  System Size       Send to Customer
                  ROI/Payback            │
                                         ▼
                              Accept → Create Project
                                    → Generate Quotation
```

### Integration Points

| When | Action |
|------|--------|
| Proposal accepted | Create Project |
| Proposal accepted | Generate Quotation |
| Project started | Create Work Orders |
| Installation complete | Invoice customer |

---

## References

- [Solar EPC Domain](../02-domain/solar-epc.md)
- [ADR-0015: Multi-Option Quotations](./0015-multi-option-quotations.md)
- [ADR-0023: Project Cost Allocation](./0023-project-cost-allocation.md)
