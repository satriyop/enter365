# Killer Features for Electrical Panel Maker Company

## The Real Pain Point: Component Selection Hell

  Imagine you're the dedicated BOM person at Vahana. A customer asks for a quote on an LVMDP 400A with 12 outgoing feeders.

  What happens today:

  ┌─────────────────────────────────────────────────────────────────────┐
  │  1. OPEN MULTIPLE PDFs                                              │
  │     📄 Schneider NSX Catalog (400 pages)                            │
  │     📄 ABB Tmax Catalog (350 pages)                                 │
  │     📄 Siemens 3VA Catalog (380 pages)                              │
  │     📄 Busbar sizing tables                                         │
  │     📄 Cable sizing tables                                          │
  │     📄 Enclosure dimension guide                                    │
  └─────────────────────────────────────────────────────────────────────┘
                                ↓
  ┌─────────────────────────────────────────────────────────────────────┐
  │  2. FOR EACH COMPONENT (50-100 items per panel):                    │
  │     - Find the right page in PDF                                    │
  │     - Match specifications (400A, 3P, 36kA breaking capacity)       │
  │     - Copy part number: "NSX400F TM400D 3P3D"                       │
  │     - Copy to spreadsheet                                           │
  │     - Look up price from separate price list                        │
  │     - Repeat for busbars, cables, terminals, accessories...         │
  └─────────────────────────────────────────────────────────────────────┘
                                ↓
  ┌─────────────────────────────────────────────────────────────────────┐
  │  3. CREATE 3 OPTIONS (customer wants choices):                      │
  │     Option A: Full Schneider (premium) → Repeat 50-100 lookups      │
  │     Option B: Full ABB (mid-tier)      → Repeat 50-100 lookups      │
  │     Option C: Mixed/Local brands       → Repeat 50-100 lookups      │
  └─────────────────────────────────────────────────────────────────────┘
                                ↓
  ┌─────────────────────────────────────────────────────────────────────┐
  │  4. RESULT: 2-3 days work for ONE quotation                         │
  │     ❌ High error rate (wrong part numbers, incompatible specs)     │
  │     ❌ Prices may be outdated                                       │
  │     ❌ Forgot accessories (cable glands, labels, etc.)              │
  │     ❌ Busbar sizing might be wrong                                 │
  └─────────────────────────────────────────────────────────────────────┘

  ---
  Does BOM Variant Solve This?

  Partially, but not the core pain.

  BOM Variant helps with:
  - ✅ Comparing multiple options side-by-side
  - ✅ Generating quotations from variants

  BOM Variant does NOT solve:
  - ❌ The lookup problem — still need to find part numbers from PDFs
  - ❌ Cross-brand equivalence — doesn't know ABB S203 ≈ Schneider iC60N ≈ Siemens 5SY4
  - ❌ Auto-calculation — doesn't size busbars based on amperage
  - ❌ Completeness check — doesn't warn "you forgot cable lugs for this connection"

  ---
## The Killer Feature: Component Cross-Reference + Panel Templates

  ---
  ### My Recommendation: Build in Phases


  Electrical components DO follow international standards. This is actually good news — we can use these standards as the "generic" definition.

  ┌─────────────────────────────────────────────────────────────────────┐
  │  IEC STANDARDS FOR ELECTRICAL COMPONENTS                           │
  ├─────────────────────────────────────────────────────────────────────┤
  │  IEC 60898  → MCB (Miniature Circuit Breaker)                      │
  │               Defines: Rating (A), Poles, Curve (B/C/D), kA        │
  │                                                                     │
  │  IEC 60947  → MCCB, Contactors, Overload Relays                    │
  │               Defines: Rating, Breaking Capacity, Utilization      │
  │                                                                     │
  │  IEC 61439  → Switchgear Assemblies (the panel itself)             │
  │               Defines: Form of separation, IP rating               │
  └─────────────────────────────────────────────────────────────────────┘

  My suggestion: Use IEC specs as the "generic component" definition. All brands comply to these specs, so cross-referencing becomes straightforward.

  Generic: "MCB 16A 1P C-Curve 6kA" (IEC 60898)
           ↓
  ┌────────────┬────────────┬────────────┬────────────┐
  │ Schneider  │ ABB        │ Siemens    │ Hager      │
  │ iC60N C16  │ S201-C16   │ 5SY4116-7  │ MC116A     │
  └────────────┴────────────┴────────────┴────────────┘
  All are 16A, 1 Pole, C-Curve, 6kA — interchangeable

  ---
  Answer 3: Who Maintains the Database?

  Ideal workflow with shared responsibility:

  ┌─────────────────────────────────────────────────────────────────────┐
  │  ROLE             │  RESPONSIBILITY                                │
  ├───────────────────┼─────────────────────────────────────────────────┤
  │  Purchasing       │  • Update prices from vendor price lists       │
  │                   │  • Add new products from catalogs              │
  │                   │  • Update stock/availability                   │
  │                   │                                                 │
  │  Engineering/     │  • Define generic component specs              │
  │  Technical        │  • Map brand equivalents (which ABB = Schneider)│
  │                   │  • Verify technical compatibility              │
  │                   │                                                 │
  │  Sales/Quotation  │  • USE the system (main beneficiary)           │
  │                   │  • Flag missing components or wrong mappings   │
  └─────────────────────────────────────────────────────────────────────┘

  Realistically for small team: One person (likely purchasing or the BOM person) does initial setup. System is designed so updates are quick — just update price, not rebuild everything.

  ---
  Answer 4: What Are the Phases?

  Let me reframe as Features, not phases:

  ┌─────────────────────────────────────────────────────────────────────┐
  │  FEATURE 1: Component Cross-Reference (FOUNDATION)                 │
  │  ─────────────────────────────────────────────────────────────────  │
  │  • Database of components with brand equivalents                   │
  │  • When you pick "MCB 16A 1P", see all brand options instantly     │
  │  • No more PDF lookup                                              │
  │  • TIME SAVED: 70% of manual lookup work                           │
  └─────────────────────────────────────────────────────────────────────┘
                                +
  ┌─────────────────────────────────────────────────────────────────────┐
  │  FEATURE 2: One-Click Brand Swap                                   │
  │  ─────────────────────────────────────────────────────────────────  │
  │  • Build BOM once with any brand                                   │
  │  • Click "Generate ABB Version" → auto-swap all components         │
  │  • Click "Generate Schneider Version" → auto-swap                  │
  │  • 3 quotation options in minutes, not days                        │
  │  • TIME SAVED: 90% of multi-brand quotation work                   │
  └─────────────────────────────────────────────────────────────────────┘
                                +
  ┌─────────────────────────────────────────────────────────────────────┐
  │  FEATURE 3: Panel Templates (OPTIONAL, ADVANCED)                   │
  │  ─────────────────────────────────────────────────────────────────  │
  │  • Pre-built structures for LVMDP, MCC, ATS                        │
  │  • Just configure: "400A, 12 feeders" → BOM auto-generated         │
  │  • For later, after Feature 1+2 prove valuable                     │
  └─────────────────────────────────────────────────────────────────────┘

  For quotation creation, you need Feature 1 + 2.

  ---
  Concrete Proposal: Data Model

  Let me show you what we'd need to build:

  ┌─────────────────────────────────────────────────────────────────────┐
  │  NEW TABLES                                                         │
  ├─────────────────────────────────────────────────────────────────────┤
  │                                                                     │
  │  component_standards (Generic component definitions)                │
  │  ├── id                                                             │
  │  ├── code: "MCB-16A-1P-C-6KA"                                       │
  │  ├── name: "MCB 16A 1 Pole C-Curve 6kA"                             │
  │  ├── category: "circuit_breaker"                                    │
  │  ├── subcategory: "mcb"                                             │
  │  ├── specs: {                                                       │
  │  │     "rating_amps": 16,                                           │
  │  │     "poles": 1,                                                  │
  │  │     "curve": "C",                                                │
  │  │     "breaking_capacity_ka": 6,                                   │
  │  │     "standard": "IEC 60898"                                      │
  │  │   }                                                              │
  │  └── unit: "pcs"                                                    │
  │                                                                     │
  │  component_brands (Brand implementations of standards)              │
  │  ├── id                                                             │
  │  ├── component_standard_id → links to generic                       │
  │  ├── brand: "schneider"                                             │
  │  ├── product_id → links to existing products table                  │
  │  ├── brand_sku: "A9F74116" (vendor's SKU)                           │
  │  ├── brand_name: "iC60N C16 1P"                                     │
  │  ├── is_preferred: true (default choice for this brand)            │
  │  └── notes: "Most common, good availability"                       │
  │                                                                     │
  └─────────────────────────────────────────────────────────────────────┘

  How It Works in Practice

  Scenario: Building LVMDP BOM

  STEP 1: Add component to BOM
  ┌─────────────────────────────────────────────────────────────────────┐
  │  Search: "MCB 16A"                                                  │
  │  ┌───────────────────────────────────────────────────────────────┐  │
  │  │  📦 MCB 16A 1P C-Curve 6kA                                    │  │
  │  │     ├─ Schneider iC60N C16    Rp 185.000  ⭐ preferred        │  │
  │  │     ├─ ABB S201-C16           Rp 165.000                      │  │
  │  │     ├─ Siemens 5SY4116-7      Rp 175.000                      │  │
  │  │     └─ Hager MC116A           Rp 95.000                       │  │
  │  │                                                                │  │
  │  │  📦 MCB 16A 1P C-Curve 10kA (higher breaking capacity)        │  │
  │  │     ├─ Schneider iC60H C16    Rp 245.000  ⭐ preferred        │  │
  │  │     ├─ ABB S201M-C16          Rp 225.000                      │  │
  │  │     └─ ...                                                    │  │
  │  └───────────────────────────────────────────────────────────────┘  │
  │                                                                     │
  │  [Select brand: Schneider ▼]  [Add to BOM]                          │
  └─────────────────────────────────────────────────────────────────────┘

  STEP 2: After building complete BOM (50 items) with Schneider
  ┌─────────────────────────────────────────────────────────────────────┐
  │  BOM: LVMDP-400A-PLN-CAWANG                                         │
  │  Brand: Schneider                                                   │
  │  Total: Rp 45.200.000                                               │
  │                                                                     │
  │  [📋 Generate ABB Version]  [📋 Generate Mixed Version]             │
  └─────────────────────────────────────────────────────────────────────┘

  STEP 3: Click "Generate ABB Version"
  ┌─────────────────────────────────────────────────────────────────────┐
  │  ✅ Created: LVMDP-400A-PLN-CAWANG (ABB)                            │
  │                                                                     │
  │  Swapped 47 components automatically                                │
  │  ⚠️  3 components have no ABB equivalent:                           │
  │     - Schneider PM5110 (Power Meter) → Manual selection needed      │
  │     - Schneider MN 220V (Shunt Trip) → Suggested: ABB UA1-220       │
  │                                                                     │
  │  New Total: Rp 41.800.000 (7.5% cheaper)                            │
  │                                                                     │
  │  [Review & Confirm]  [Edit Manually]                                │
  └─────────────────────────────────────────────────────────────────────┘

  ---
  What This Means for Quotation Workflow

  BEFORE (Current)                    AFTER (With Feature)
  ─────────────────────────────────   ─────────────────────────────────
  Day 1: Build Schneider BOM          Hour 1: Build BOM once
         - 50 PDF lookups                     - Search & select components
         - Copy/paste each item               - No PDF lookup needed

  Day 2: Build ABB BOM                Hour 1.5: Click "Generate ABB"
         - 50 more PDF lookups                - Auto-swap
         - Copy/paste again                   - Review 3-5 exceptions

  Day 3: Build Mixed/Economy BOM      Hour 2: Click "Generate Economy"
         - 50 more lookups                    - Auto-swap to cheaper brands
         - Finalize quotation                 - Done!

  TOTAL: 2-3 days                     TOTAL: 2-3 hours



