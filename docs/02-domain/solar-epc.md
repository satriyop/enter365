---
section: domain
title: "Solar EPC"
order: 5
entities: [SolarProposal, PlnTariff, IndonesiaSolarData]
services: [SolarProposalService, SolarCalculationService]
---

# Solar EPC

> **Solar proposal and ROI calculation system**
>
> Killer feature for solar EPC contractors in Indonesia.

---

## AI Agent Quick Reference

**Use this document when:**
- Implementing solar features
- Working with ROI calculations
- Understanding PLN tariffs
- Building the customer portal

**Key models:** `SolarProposal`, `PlnTariff`, `IndonesiaSolarData`
**Key services:** `SolarProposalService`, `SolarCalculationService`

---

## Solar Proposal Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SOLAR PROPOSAL FLOW                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐             │
│   │  SITE    │───▶│ PROPOSAL │───▶│ CUSTOMER │───▶│ PROJECT  │             │
│   │ASSESSMENT│    │GENERATION│    │ ACCEPTS  │    │ CREATED  │             │
│   └────┬─────┘    └────┬─────┘    └────┬─────┘    └────┬─────┘             │
│        │               │               │               │                    │
│        │               │               │               │                    │
│   ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐    ┌────▼─────┐             │
│   │ Customer │    │   Auto   │    │ Public   │    │ Quotation│             │
│   │   Data   │    │Calculate:│    │  Portal  │    │ & Invoice│             │
│   │ - Usage  │    │- System  │    │  Link    │    │          │             │
│   │ - Tariff │    │- ROI     │    │          │    │          │             │
│   │ - Roof   │    │- Payback │    │          │    │          │             │
│   └──────────┘    └──────────┘    └──────────┘    └──────────┘             │
│                                                                             │
│   PUBLIC CALCULATOR:                                                        │
│   ┌────────────────────────────────────────┐                               │
│   │  Website Visitor → Quick Estimate      │                               │
│   │  (No login required)                   │                               │
│   └────────────────────────────────────────┘                               │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Why This Feature Matters

### Industry Pain Points

1. **Complex Calculations** - ROI depends on many factors
2. **Customer Education** - Customers don't understand solar economics
3. **Professional Proposals** - Need impressive documents
4. **Follow-up Tracking** - Long sales cycles
5. **PLN Tariff Complexity** - Different rates for different customer types

### Enter365 Solution

- Automated system sizing based on consumption
- Instant ROI and payback calculations
- Professional PDF proposals
- Customer self-service portal
- PLN tariff database
- Indonesia solar irradiance data

---

## Solar Proposal

### Purpose
Complete solar PV system proposal with financial analysis.

### Key Fields

```php
// File: /app/Models/Accounting/SolarProposal.php

// Identification
$table->string('proposal_number');            // SP-202401-0001
$table->string('public_token');               // For customer portal link
$table->foreignId('contact_id');

// Site Information
$table->string('site_address');
$table->string('site_type');                  // residential, commercial, industrial
$table->decimal('roof_area_m2', 10, 2);
$table->string('roof_orientation');           // north, south, east, west
$table->decimal('roof_tilt_degrees', 5, 2);

// Current Electricity Usage
$table->string('pln_tariff_code');            // R1/1300, B2/23000, etc.
$table->decimal('monthly_kwh', 10, 2);
$table->bigInteger('monthly_bill');           // Current PLN bill

// Proposed System
$table->decimal('system_size_kwp', 10, 2);    // Calculated system size
$table->integer('panel_count');
$table->string('panel_brand');
$table->string('inverter_brand');
$table->decimal('annual_production_kwh', 12, 2);

// Financial Analysis
$table->bigInteger('system_cost');            // Total installation cost
$table->bigInteger('monthly_savings');        // Estimated savings
$table->bigInteger('annual_savings');
$table->decimal('payback_years', 5, 2);       // Years to break even
$table->decimal('roi_percent', 5, 2);         // Return on investment
$table->integer('system_lifetime_years');     // Typically 25 years
$table->bigInteger('lifetime_savings');

// Status
$table->string('status');                     // draft, sent, viewed, accepted, rejected
$table->timestamp('sent_at');
$table->timestamp('viewed_at');
$table->timestamp('accepted_at');
$table->timestamp('rejected_at');
$table->text('rejection_reason');
```

### Status Flow

```
Draft → Sent → Viewed → Accepted → Project Created
              └──────→ Rejected
```

---

## Solar Calculation

### System Sizing

```php
// File: /app/Services/Accounting/SolarCalculationService.php

public function calculateSystemSize(array $input): array
{
    // 1. Get monthly consumption
    $monthlyKwh = $input['monthly_kwh'];

    // 2. Get solar irradiance for location
    $irradiance = $this->getIrradiance($input['latitude'], $input['longitude']);
    // Indonesia average: 4.5 - 5.5 kWh/m²/day

    // 3. Calculate required system size
    // Daily consumption ÷ peak sun hours ÷ system efficiency
    $dailyKwh = $monthlyKwh / 30;
    $peakSunHours = $irradiance; // kWh/m²/day ≈ peak sun hours
    $systemEfficiency = 0.80; // 80% (losses from heat, inverter, etc.)

    $systemSizeKwp = $dailyKwh / $peakSunHours / $systemEfficiency;

    // 4. Round up to panel increments
    $panelWattage = 550; // Typical modern panel
    $panelCount = ceil(($systemSizeKwp * 1000) / $panelWattage);
    $actualSystemSize = ($panelCount * $panelWattage) / 1000;

    return [
        'system_size_kwp' => $actualSystemSize,
        'panel_count' => $panelCount,
        'annual_production_kwh' => $actualSystemSize * $peakSunHours * 365 * $systemEfficiency,
    ];
}
```

### ROI Calculation

```php
public function calculateFinancials(array $input): array
{
    $systemCost = $input['system_cost'];
    $annualProduction = $input['annual_production_kwh'];
    $tariffPerKwh = $this->getTariffRate($input['pln_tariff_code']);

    // Annual savings
    $annualSavings = $annualProduction * $tariffPerKwh;

    // Simple payback
    $paybackYears = $systemCost / $annualSavings;

    // ROI over system lifetime (25 years)
    $lifetimeYears = 25;
    $lifetimeSavings = $annualSavings * $lifetimeYears;
    $roi = (($lifetimeSavings - $systemCost) / $systemCost) * 100;

    // Account for panel degradation (0.5% per year)
    $degradationRate = 0.005;
    $adjustedLifetimeSavings = 0;
    for ($year = 1; $year <= $lifetimeYears; $year++) {
        $yearProduction = $annualProduction * pow(1 - $degradationRate, $year - 1);
        $adjustedLifetimeSavings += $yearProduction * $tariffPerKwh;
    }

    return [
        'monthly_savings' => $annualSavings / 12,
        'annual_savings' => $annualSavings,
        'payback_years' => $paybackYears,
        'roi_percent' => $roi,
        'lifetime_savings' => $adjustedLifetimeSavings,
    ];
}
```

---

## PLN Tariffs

### Purpose
Indonesian electricity tariff database for accurate calculations.

### Key Fields

```php
// File: /app/Models/Accounting/PlnTariff.php

$table->string('code');                       // R1/1300, B2/23000
$table->string('category');                   // residential, commercial, industrial
$table->string('name');                       // "Rumah Tangga 1300 VA"
$table->integer('power_va');                  // 1300, 2200, 23000
$table->bigInteger('rate_per_kwh');           // Rate in cents
$table->bigInteger('subscription_fee');       // Monthly fixed fee
$table->date('effective_from');
$table->date('effective_until');
```

### Tariff Categories

| Code Pattern | Category | Description |
|--------------|----------|-------------|
| R1/xxx | Residential | Small households |
| R2/xxx | Residential | Medium households |
| R3/xxx | Residential | Large households |
| B1/xxx | Business | Small business |
| B2/xxx | Business | Medium business |
| B3/xxx | Business | Large business |
| I1/xxx | Industrial | Small industry |
| I2/xxx | Industrial | Medium industry |
| I3/xxx | Industrial | Large industry |

---

## Indonesia Solar Data

### Purpose
Solar irradiance data by region for accurate production estimates.

### Key Fields

```php
// File: /app/Models/Accounting/IndonesiaSolarData.php

$table->string('province');
$table->string('city');
$table->decimal('latitude', 10, 6);
$table->decimal('longitude', 10, 6);
$table->decimal('avg_daily_irradiance', 5, 2);  // kWh/m²/day
$table->decimal('jan_irradiance', 5, 2);        // Monthly averages
$table->decimal('feb_irradiance', 5, 2);
// ... all 12 months
```

### Regional Variations

| Region | Avg Irradiance | Notes |
|--------|---------------|-------|
| Eastern Indonesia | 5.0-5.5 kWh/m²/day | Best solar potential |
| Central Indonesia | 4.5-5.0 kWh/m²/day | Good |
| Western Indonesia | 4.0-4.5 kWh/m²/day | More rainfall |

---

## Customer Portal

### Public Proposal Link

Customers can view their proposal without logging in:

```
https://app.enter365.id/solar/{public_token}
```

### Portal Features

- View full proposal details
- See financial projections
- Accept or reject proposal
- Download PDF
- Request modifications

### API Endpoints (Public)

```
GET  /api/v1/public/solar-proposals/{token}      # View proposal
POST /api/v1/public/solar-proposals/{token}/accept
POST /api/v1/public/solar-proposals/{token}/reject
```

---

## Public Calculator

### Purpose
Marketing tool - visitors get instant solar estimates.

### Features

- No login required
- Input: location, monthly bill, roof area
- Output: estimated system size, savings, payback
- CTA: "Get detailed proposal"

### API Endpoints (Public)

```
POST /api/v1/public/solar-calculator/calculate
GET  /api/v1/public/solar-calculator/tariffs
```

### Request Example

```json
{
    "province": "DKI Jakarta",
    "monthly_bill": 2500000,
    "pln_tariff_code": "R2/3500",
    "roof_area_m2": 50,
    "roof_orientation": "north"
}
```

### Response Example

```json
{
    "estimated_system_size_kwp": 5.5,
    "estimated_panel_count": 10,
    "estimated_cost_range": {
        "min": 55000000,
        "max": 77000000
    },
    "estimated_monthly_savings": 1800000,
    "estimated_payback_years": 3.5,
    "estimated_roi_percent": 286,
    "note": "Contact us for detailed proposal"
}
```

---

## Proposal Generation

### Service Method

```php
// File: /app/Services/Accounting/SolarProposalService.php

public function create(array $data): SolarProposal
{
    return DB::transaction(function () use ($data) {
        // 1. Calculate system sizing
        $sizing = $this->calculationService->calculateSystemSize($data);

        // 2. Calculate financials
        $financials = $this->calculationService->calculateFinancials([
            'system_cost' => $data['system_cost'],
            'annual_production_kwh' => $sizing['annual_production_kwh'],
            'pln_tariff_code' => $data['pln_tariff_code'],
        ]);

        // 3. Create proposal
        $proposal = SolarProposal::create([
            'proposal_number' => $this->generateNumber(),
            'public_token' => Str::random(32),
            'contact_id' => $data['contact_id'],
            ...$data,
            ...$sizing,
            ...$financials,
        ]);

        return $proposal;
    });
}

public function accept(SolarProposal $proposal): SolarProposal
{
    $proposal->update([
        'status' => 'accepted',
        'accepted_at' => now(),
    ]);

    // Optionally create project and quotation
    if (config('features.modules.projects')) {
        $this->createProject($proposal);
    }

    return $proposal;
}
```

---

## API Endpoints

### Solar Proposals (Authenticated)

```
GET    /api/v1/solar-proposals
POST   /api/v1/solar-proposals
GET    /api/v1/solar-proposals/{id}
PUT    /api/v1/solar-proposals/{id}
DELETE /api/v1/solar-proposals/{id}

POST   /api/v1/solar-proposals/{id}/send       # Send to customer
POST   /api/v1/solar-proposals/{id}/duplicate
GET    /api/v1/solar-proposals/{id}/pdf        # Download PDF
```

### Solar Data

```
GET    /api/v1/solar-data/tariffs              # PLN tariffs
GET    /api/v1/solar-data/irradiance           # Solar irradiance by location
```

---

## Integration with Other Modules

### Project Creation

When proposal is accepted:
1. Create Project for installation tracking
2. Create Quotation from proposal
3. Track installation costs vs. proposal

### BOM for Installation

Solar installation can have BOMs:
- Solar panels
- Inverters
- Mounting structure
- Cables and connectors
- Installation labor

---

## Related Documentation

- [ADR-0025: Solar Proposal System](../08-adr/0025-solar-proposal-system.md)
- [Sales Cycle](./sales-cycle.md)
- [Indonesian Context](./indonesian-context.md)
