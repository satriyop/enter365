# Bom Variant

# Internal Vs Customer Facing BoM Variant

  Key Difference: Internal vs Customer-Facing

  | Aspect      | Internal Cost Planning              | Customer Quotation                |
  |-------------|-------------------------------------|-----------------------------------|
  | Purpose     | Decide which BOM to manufacture     | Help customer choose & close sale |
  | Users       | Procurement, Engineering, Finance   | Sales Team, Customer              |
  | Data shown  | Raw costs, margins, supplier prices | Selling price, features, warranty |
  | Sensitivity | Shows profit margin, supplier names | Hides internal costs              |
  | Output      | Internal decision                   | Customer selection → Invoice      |

  Data Model Difference

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │                         INTERNAL VIEW (Current)                              │
  ├─────────────────────────────────────────────────────────────────────────────┤
  │                                                                             │
  │  BOM: Premium PLTS 50kWp                                                    │
  │  ────────────────────────────────────────────────────────────────────────── │
  │  Material Cost:      Rp 800,000,000    ◄── Raw supplier costs              │
  │  Labor Cost:         Rp  80,000,000    ◄── Internal labor rates            │
  │  Overhead:           Rp  40,000,000    ◄── Allocated overhead              │
  │  ────────────────────────────────────────────────────────────────────────── │
  │  Total Cost:         Rp 920,000,000                                         │
  │  Target Margin:      25%               ◄── Internal only                   │
  │  Selling Price:      Rp 1,150,000,000  ◄── Calculated                      │
  │                                                                             │
  └─────────────────────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │                       CUSTOMER VIEW (Quotation)                              │
  ├─────────────────────────────────────────────────────────────────────────────┤
  │                                                                             │
  │  Option: Premium PLTS 50kWp                                                 │
  │  ────────────────────────────────────────────────────────────────────────── │
  │  Inverter:           SMA Sunny Tripower X 50kW                              │
  │  Solar Panels:       LONGi Hi-MO 5 (92 units)                               │
  │  Mounting:           Schletter Premium Rail                                 │
  │  ────────────────────────────────────────────────────────────────────────── │
  │  Warranty:           25 years                                                │
  │  Efficiency:         92%                                                     │
  │  ────────────────────────────────────────────────────────────────────────── │
  │  Price:              Rp 1,150,000,000  ◄── Only final price shown          │
  │                                                                             │
  │  [SELECT THIS OPTION]                                                       │
  │                                                                             │
  └─────────────────────────────────────────────────────────────────────────────┘

  UI/UX Proposal

  1. Internal Cost Planning UI (Back Office)

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │  BOM Variant Comparison - PLTS 50kWp                        [Export Excel]  │
  ├─────────────────────────────────────────────────────────────────────────────┤
  │                                                                             │
  │  ┌─ Cost Breakdown Table ─────────────────────────────────────────────────┐ │
  │  │                    │   Budget    │  Standard   │   Premium   │  Diff   │ │
  │  │────────────────────┼─────────────┼─────────────┼─────────────┼─────────│ │
  │  │ MATERIAL           │             │             │             │         │ │
  │  │  └ Inverter        │  50,000,000 │  85,000,000 │ 120,000,000 │  +140%  │ │
  │  │  └ Panels (92x)    │ 138,000,000 │ 165,600,000 │ 202,400,000 │   +47%  │ │
  │  │  └ Mounting        │  45,000,000 │  45,000,000 │  78,000,000 │   +73%  │ │
  │  │  └ Cables & BOS    │  42,000,000 │  49,400,000 │  59,600,000 │   +42%  │ │
  │  │────────────────────┼─────────────┼─────────────┼─────────────┼─────────│ │
  │  │ Subtotal Material  │ 275,000,000 │ 345,000,000 │ 460,000,000 │   +67%  │ │
  │  │────────────────────┼─────────────┼─────────────┼─────────────┼─────────│ │
  │  │ LABOR              │  50,000,000 │  65,000,000 │  80,000,000 │   +60%  │ │
  │  │ OVERHEAD           │  25,000,000 │  35,000,000 │  40,000,000 │   +60%  │ │
  │  │════════════════════╪═════════════╪═════════════╪═════════════╪═════════│ │
  │  │ TOTAL COST         │ 350,000,000 │ 445,000,000 │ 580,000,000 │   +66%  │ │
  │  │────────────────────┼─────────────┼─────────────┼─────────────┼─────────│ │
  │  │ Target Margin      │     [25%]   │     [25%]   │     [25%]   │         │ │
  │  │ SELLING PRICE      │ 437,500,000 │ 556,250,000 │ 725,000,000 │   +66%  │ │
  │  └────────────────────────────────────────────────────────────────────────┘ │
  │                                                                             │
  │  ┌─ Actions ──────────────────────────────────────────────────────────────┐ │
  │  │  [Create Quotation from Variants]  [Clone to New Product]              │ │
  │  └────────────────────────────────────────────────────────────────────────┘ │
  │                                                                             │
  └─────────────────────────────────────────────────────────────────────────────┘

  Key Features:
  - Detailed cost breakdown by category
  - Editable margin per variant
  - Percentage difference column
  - Export to Excel
  - Action: "Create Quotation from Variants"

  ---
  2. Quotation Creation UI (Sales Team)

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │  New Quotation                                              [Save Draft]    │
  ├─────────────────────────────────────────────────────────────────────────────┤
  │                                                                             │
  │  Customer: [PT Energi Masa Depan ▼]     Contact: [Budi Santoso ▼]          │
  │  Project:  [PLTS Rooftop 50kWp    ]     Valid Until: [2025-01-27]          │
  │                                                                             │
  │  ┌─ Quotation Type ───────────────────────────────────────────────────────┐ │
  │  │  ○ Single Option (standard quotation)                                  │ │
  │  │  ● Multiple Options (from BOM Variant Group)                           │ │
  │  │                                                                         │ │
  │  │    Select Variant Group: [PLTS 50kWp Material Options ▼]               │ │
  │  └────────────────────────────────────────────────────────────────────────┘ │
  │                                                                             │
  │  ┌─ Configure Options for Customer ──────────────────────────────────────┐ │
  │  │                                                                        │ │
  │  │  ☑ Budget     Display Name: [Paket Hemat        ]  Price: 437,500,000 │ │
  │  │               Tagline:      [Solusi terjangkau untuk memulai          ]│ │
  │  │                                                                        │ │
  │  │  ☑ Standard   Display Name: [Paket Optimal      ]  Price: 556,250,000 │ │
  │  │               Tagline:      [Keseimbangan harga dan performa          ]│ │
  │  │               ★ Mark as Recommended                                    │ │
  │  │                                                                        │ │
  │  │  ☑ Premium    Display Name: [Paket Premium      ]  Price: 725,000,000 │ │
  │  │               Tagline:      [Performa terbaik, garansi terpanjang     ]│ │
  │  │                                                                        │ │
  │  └────────────────────────────────────────────────────────────────────────┘ │
  │                                                                             │
  │  ┌─ Additional Info per Option ──────────────────────────────────────────┐ │
  │  │  [Edit Features & Highlights]  [Edit Warranty Terms]  [Edit Specs]    │ │
  │  └────────────────────────────────────────────────────────────────────────┘ │
  │                                                                             │
  │           [Preview Customer View]        [Send Quotation]                   │
  │                                                                             │
  └─────────────────────────────────────────────────────────────────────────────┘

  Key Features:
  - Select "Multiple Options" quotation type
  - Link to BOM Variant Group
  - Customize display names & taglines (not internal names)
  - Mark recommended option
  - Override selling price if needed
  - Preview what customer sees

  ---
  3. Customer-Facing Quotation UI (What Customer Sees)

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │                                                                             │
  │                    [COMPANY LOGO]                                           │
  │                    PT Solar Indonesia                                       │
  │                                                                             │
  │  ═══════════════════════════════════════════════════════════════════════   │
  │                                                                             │
  │                         PENAWARAN HARGA                                     │
  │                    PLTS Rooftop 50 kWp                                      │
  │                                                                             │
  │               Kepada: PT Energi Masa Depan                                  │
  │               Tanggal: 27 Desember 2024                                     │
  │               Berlaku hingga: 27 Januari 2025                               │
  │                                                                             │
  │  ═══════════════════════════════════════════════════════════════════════   │
  │                                                                             │
  │        Silakan pilih paket yang sesuai dengan kebutuhan Anda:              │
  │                                                                             │
  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐             │
  │  │   PAKET HEMAT   │  │  PAKET OPTIMAL  │  │  PAKET PREMIUM  │             │
  │  │                 │  │   ★ RECOMMENDED │  │                 │             │
  │  │─────────────────│  │─────────────────│  │─────────────────│             │
  │  │                 │  │                 │  │                 │             │
  │  │ Inverter:       │  │ Inverter:       │  │ Inverter:       │             │
  │  │ Growatt 50kW    │  │ Huawei SUN2000  │  │ SMA Tripower    │             │
  │  │                 │  │                 │  │                 │             │
  │  │ Panel:          │  │ Panel:          │  │ Panel:          │             │
  │  │ Canadian Solar  │  │ JA Solar 550W   │  │ LONGi Hi-MO 5   │             │
  │  │ 545W (92 unit)  │  │ (92 unit)       │  │ (92 unit)       │             │
  │  │                 │  │                 │  │                 │             │
  │  │ Mounting:       │  │ Mounting:       │  │ Mounting:       │             │
  │  │ NUSA Standard   │  │ NUSA Standard   │  │ Schletter       │             │
  │  │                 │  │                 │  │                 │             │
  │  │─────────────────│  │─────────────────│  │─────────────────│             │
  │  │                 │  │                 │  │                 │             │
  │  │ ✓ Garansi 10th  │  │ ✓ Garansi 15th  │  │ ✓ Garansi 25th  │             │
  │  │ ✓ Efisiensi 80% │  │ ✓ Efisiensi 85% │  │ ✓ Efisiensi 92% │             │
  │  │ ✓ Monitoring    │  │ ✓ Monitoring+   │  │ ✓ Smart Monitor │             │
  │  │                 │  │ ✓ Maintenance   │  │ ✓ Maintenance   │             │
  │  │                 │  │   1 tahun       │  │   3 tahun       │             │
  │  │                 │  │                 │  │                 │             │
  │  │─────────────────│  │─────────────────│  │─────────────────│             │
  │  │                 │  │                 │  │                 │             │
  │  │ Rp 437.500.000  │  │ Rp 556.250.000  │  │ Rp 725.000.000  │             │
  │  │                 │  │                 │  │                 │             │
  │  │  [PILIH INI]    │  │  [PILIH INI]    │  │  [PILIH INI]    │             │
  │  │                 │  │                 │  │                 │             │
  │  └─────────────────┘  └─────────────────┘  └─────────────────┘             │
  │                                                                             │
  │  ───────────────────────────────────────────────────────────────────────   │
  │                                                                             │
  │   Semua paket termasuk:                                                     │
  │   • Survei lokasi & desain sistem                                          │
  │   • Instalasi & commissioning                                               │
  │   • Pengurusan ijin PLN                                                     │
  │   • Training operator                                                       │
  │                                                                             │
  └─────────────────────────────────────────────────────────────────────────────┘

  Key Features:
  - Clean, professional design
  - Side-by-side comparison cards
  - Highlighted "Recommended" option
  - Feature checkmarks (not cost breakdown)
  - Clear pricing
  - "Select This" buttons
  - Shared inclusions at bottom

  ---
  New Data Structure Needed

  // Quotation Model - New Fields
  'variant_group_id'     => nullable, FK to bom_variant_groups
  'quotation_type'       => 'single' | 'multi_option'
  'selected_variant_id'  => nullable, FK to boms (after customer selects)

  // New Table: quotation_variant_options
  - quotation_id
  - bom_id
  - display_name          // "Paket Hemat" instead of "Budget"
  - tagline               // Marketing text
  - is_recommended        // Star marker
  - selling_price         // Can override BOM calculated price
  - features              // JSON: ["Garansi 10th", "Efisiensi 80%"]
  - sort_order

  ---
  Summary: Two Different UIs

  | Aspect   | Internal Planning                 | Customer Quotation                |
  |----------|-----------------------------------|-----------------------------------|
  | Layout   | Data table with all costs         | Cards with features               |
  | Shows    | Material/Labor/Overhead breakdown | Selling price only                |
  | Editable | Costs, margins                    | Display names, taglines, features |
  | Actions  | Export Excel, Create Quotation    | Select option                     |
  | Tone     | Analytical                        | Marketing/Sales                   |