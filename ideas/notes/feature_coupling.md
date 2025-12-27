# Feature Coupling Analysis & Modular Deployment Strategy

  1. Feature Coupling Matrix

                   ┌───────────────────────────────────────────────────────────────────────────────┐
                   │                         DEPENDS ON (→)                                        │
                   ├────────┬────────┬────────┬────────┬────────┬────────┬────────┬────────┬──────┤
    FEATURE (↓)    │Account │Contact │Product │Warehouse│Invoice │Bill    │BOM     │WorkOrder│MRP  │
  ├────────────────┼────────┼────────┼────────┼────────┼────────┼────────┼────────┼────────┼──────┤
  │ Account        │   -    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Contact        │   ○    │   -    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Journal Entry  │   ●    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Product        │   ●    │   ○    │   -    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Invoice        │   ●    │   ●    │   ◐    │   ○    │   -    │   ○    │   ○    │   ○    │  ○   │
  │ Bill           │   ●    │   ●    │   ◐    │   ○    │   ○    │   -    │   ○    │   ○    │  ○   │
  │ Payment        │   ●    │   ●    │   ○    │   ○    │   ◐    │   ◐    │   ○    │   ○    │  ○   │
  │ Quotation      │   ●    │   ●    │   ◐    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Purchase Order │   ●    │   ●    │   ●    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Delivery Order │   ○    │   ●    │   ●    │   ●    │   ●    │   ○    │   ○    │   ○    │  ○   │
  │ GRN            │   ○    │   ○    │   ●    │   ●    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Inventory      │   ●    │   ○    │   ●    │   ●    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ BOM            │   ○    │   ○    │   ●    │   ○    │   ○    │   ○    │   -    │   ○    │  ○   │
  │ Work Order     │   ○    │   ○    │   ●    │   ●    │   ○    │   ○    │   ◐    │   -    │  ○   │
  │ Mat. Requisition│  ○    │   ○    │   ●    │   ●    │   ○    │   ○    │   ○    │   ●    │  ○   │
  │ MRP            │   ○    │   ○    │   ●    │   ○    │   ○    │   ○    │   ●    │   ●    │  -   │
  │ Project        │   ◐    │   ●    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Budget         │   ●    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  │ Subcontracting │   ○    │   ●    │   ●    │   ○    │   ○    │   ○    │   ○    │   ○    │  ○   │
  └────────────────┴────────┴────────┴────────┴────────┴────────┴────────┴────────┴────────┴──────┘

  Legend: ● Required  ◐ Optional  ○ No dependency

  ---
  2. Customer Profile Packages

  Based on Indonesian SME market needs, here are recommended packages:

  | Package    | Target Customer    | Enabled Features                                  | Disabled Features                               |
  |------------|--------------------|---------------------------------------------------|-------------------------------------------------|
  | Starter    | Jasa/Konsultan     | Account, Contact, Invoice, Bill, Payment, Journal | Product, Inventory, Manufacturing, MRP, Project |
  | Dagang     | Distributor/Retail | Above + Product, PO, Inventory, Stock Opname      | Manufacturing, MRP, Subcontracting              |
  | Proyek     | Kontraktor/EPC     | Starter + Product, Project, Quotation             | Inventory*, Manufacturing, MRP                  |
  | Manufaktur | Pabrik             | All except MRP                                    | MRP (advanced add-on)                           |
  | Enterprise | Full ERP           | All features enabled                              | None                                            |

  *Proyek package can optionally enable simplified inventory

  ---
  3. Recommended Implementation: Feature Flag System

  I recommend a configuration-based approach rather than removing code:

  // config/features.php
  return [
      'modules' => [
          // Core - always enabled
          'accounting' => true,
          'contacts' => true,

          // Configurable modules
          'products' => env('FEATURE_PRODUCTS', true),
          'inventory' => env('FEATURE_INVENTORY', true),
          'quotations' => env('FEATURE_QUOTATIONS', true),
          'purchase_orders' => env('FEATURE_PURCHASE_ORDERS', true),
          'delivery_orders' => env('FEATURE_DELIVERY_ORDERS', true),
          'grn' => env('FEATURE_GRN', true),
          'projects' => env('FEATURE_PROJECTS', true),
          'manufacturing' => env('FEATURE_MANUFACTURING', false),
          'mrp' => env('FEATURE_MRP', false),
          'subcontracting' => env('FEATURE_SUBCONTRACTING', false),
          'budgeting' => env('FEATURE_BUDGETING', true),
          'recurring' => env('FEATURE_RECURRING', true),
          'multi_currency' => env('FEATURE_MULTI_CURRENCY', false),
      ],
  ];

  ---
  4. UX Implications When Features Are Disabled

  | Disabled Feature | UX Impact                                   | Mitigation Strategy                                         |
  |------------------|---------------------------------------------|-------------------------------------------------------------|
  | Products         | Invoice/Bill items manual entry only        | Show "Service Item" quick-add, allow free-text descriptions |
  | Inventory        | No stock tracking, no COGS auto-calculation | Hide "Stock" columns, use manual expense posting            |
  | Quotations       | No sales pipeline workflow                  | Direct invoice creation, hide Quote→Invoice conversion      |
  | PO               | No purchase approval workflow               | Direct bill creation, simplified purchasing                 |
  | Delivery Order   | No shipment tracking                        | Invoice = delivery assumed, hide shipping tab               |
  | GRN              | No receiving workflow                       | Bill posting = received, no partial receiving               |
  | Projects         | No project cost tracking                    | Hide project dropdowns on Invoice/Bill forms                |
  | Manufacturing    | No production workflow                      | Hide BOM, Work Order menus entirely                         |
  | MRP              | No demand planning                          | Hide MRP menu, manual reorder decisions                     |

  ---
  5. Critical UX Principles for Disabled Features

  ┌─────────────────────────────────────────────────────────────────────┐
  │                    UX PRINCIPLES FOR DISABLED FEATURES              │
  ├─────────────────────────────────────────────────────────────────────┤
  │                                                                     │
  │  1. INVISIBLE, NOT BROKEN                                           │
  │     ✓ Hide menu items completely                                    │
  │     ✓ Hide related form fields (not disable/grey out)              │
  │     ✓ Remove related columns from tables                           │
  │     ✗ Don't show empty states for disabled features                │
  │                                                                     │
  │  2. SIMPLIFIED WORKFLOWS                                            │
  │     ✓ Invoice without requiring Product selection                   │
  │     ✓ Bill without requiring PO reference                          │
  │     ✓ Skip optional steps (GRN, DO) in document flow               │
  │                                                                     │
  │  3. SMART DEFAULTS                                                  │
  │     ✓ Auto-select default accounts when inventory disabled         │
  │     ✓ Single warehouse mode when multi-warehouse disabled          │
  │     ✓ IDR only when multi-currency disabled                        │
  │                                                                     │
  │  4. GRACEFUL API RESPONSES                                          │
  │     ✓ Return 404 for disabled feature endpoints                    │
  │     ✓ Filter out disabled fields from API resources                │
  │     ✓ Adjust validation rules based on enabled features            │
  │                                                                     │
  └─────────────────────────────────────────────────────────────────────┘

  ---
  6. PT NEK Scenario: Service Company Without Inventory/MRP

  For a company like PT NEK (small team, service-based, no inventory):

  Recommended Package: Starter or Proyek

  Configuration:
  FEATURE_PRODUCTS=true          # For service catalog
  FEATURE_INVENTORY=false        # No stock tracking
  FEATURE_QUOTATIONS=true        # Sales pipeline
  FEATURE_PURCHASE_ORDERS=false  # Direct bill entry
  FEATURE_DELIVERY_ORDERS=false  # No shipping
  FEATURE_GRN=false              # No receiving
  FEATURE_PROJECTS=true          # If project-based billing
  FEATURE_MANUFACTURING=false
  FEATURE_MRP=false
  FEATURE_SUBCONTRACTING=false

  UX Changes for PT NEK:

  | Area         | Full Version                    | PT NEK Version                  |
  |--------------|---------------------------------|---------------------------------|
  | Product form | Unit, stock tracking, warehouse | Unit only, service type default |
  | Invoice form | Product picker with stock check | Service item dropdown, no stock |
  | Bill form    | PO reference, GRN link          | Simple expense entry            |
  | Sidebar menu | 15+ menu items                  | 8 core menus                    |
  | Reports      | Full inventory/manufacturing    | Financial reports only          |

  ---
  7. Implementation Recommendation

  Phase 1: Feature Flag Infrastructure (1-2 weeks)
  // Middleware to check feature access
  class FeatureEnabled
  {
      public function handle($request, Closure $next, string $feature)
      {
          if (!config("features.modules.{$feature}", false)) {
              abort(404, "Feature not available");
          }
          return $next($request);
      }
  }

  // Blade directive for UI
  @feature('inventory')
      <x-nav-item route="inventory.index" icon="box" label="Inventory" />
  @endfeature

  // Model scope for queries
  Product::query()
      ->when(!feature('inventory'), fn($q) => $q->where('track_inventory', false))

  Phase 2: Menu & Form Simplification (2-3 weeks)
  - Conditional menu rendering
  - Dynamic form field visibility
  - API resource field filtering

  Phase 3: Validation & Business Logic (1-2 weeks)
  - Conditional validation rules
  - Skip optional workflow steps
  - Default account assignments

  ---
  8. Balancing Quality & Market Needs

  | Concern              | Approach                                              |
  |----------------------|-------------------------------------------------------|
  | Code Maintainability | Single codebase, feature flags, not multiple versions |
  | Testing              | Test matrix for each package configuration            |
  | Customer Upgrades    | Enable features without data migration                |
  | Pricing Strategy     | Base + add-on modules pricing model                   |
  | Support Complexity   | Document per-package behavior differences             |

  ★ Insight ─────────────────────────────────────
  Strategic Value: Feature modularity allows you to:
  1. Lower entry barrier - SMEs can start with Starter package (lower price, simpler UX)
  2. Upsell path - Enable features as customer grows
  3. Faster onboarding - Less training needed for simplified packages
  4. Competitive positioning - "Pay only for what you use" vs bloated ERP alternatives

  Anti-pattern to avoid: Don't create separate codebases per customer. Use configuration, not fork. This maintains quality and reduces technical debt.

  ─────────────────────────────────────────────────


  1. Define package tiers with clear feature boundaries
  2. Create config/features.php with module flags
  3. Build @feature Blade directive for conditional UI
  4. Add middleware for API route protection
  5. Update factories/seeders to respect feature flags for testing
  6. Document each package's workflow differences