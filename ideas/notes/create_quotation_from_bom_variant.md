# Create Quotation from BOM

  New Endpoint

  POST /api/v1/quotations/from-bom

  Request Parameters

  | Parameter      | Required | Description                                            |
  |----------------|----------|--------------------------------------------------------|
  | bom_id         | Yes      | Active BOM to create quotation from                    |
  | contact_id     | Yes      | Customer contact                                       |
  | margin_percent | No       | Margin on BOM cost (default: 20%)                      |
  | selling_price  | No       | Override: direct selling price                         |
  | expand_items   | No       | true = expand BOM items, false = single line (default) |
  | quotation_date | No       | Quotation date                                         |
  | valid_until    | No       | Validity date                                          |
  | subject        | No       | Custom subject (defaults to BOM name + variant)        |
  | tax_rate       | No       | Tax rate percentage                                    |

  Example Usage

  // Simple - pick Standard variant, 25% margin
  {
      "bom_id": 5,
      "contact_id": 123,
      "margin_percent": 25
  }

  // Detailed - expand all BOM items
  {
      "bom_id": 5,
      "contact_id": 123,
      "expand_items": true,
      "margin_percent": 20
  }

  Test Results

  - 18 tests passing (50 assertions)
  - Covers: creation, margins, validation, optional parameters

  Files Changed

  - app/Services/Accounting/QuotationService.php - Added createFromBom() method
  - app/Http/Controllers/Api/V1/QuotationController.php - Added fromBom() endpoint
  - app/Http/Requests/Api/V1/StoreQuotationFromBomRequest.php - New validation class
  - app/Models/Accounting/Quotation.php - Added source_bom_id field & sourceBom() relationship
  - routes/api.php - Added route
  - database/migrations/..._add_source_bom_id_to_quotations_table.php - New column
  - api.json - Updated OpenAPI spec

  ★ Insight ─────────────────────────────────────
  The expand_items option gives salespeople flexibility:
  - false (default): Clean single-line quote showing just the finished product and total price - ideal for customer-facing proposals
  - true: Detailed breakdown showing all materials/labor/overhead - useful for transparency or negotiation
  ─────────────────────────────────────────────────

  Now salespeople have two paths from the BOM Variant Comparison screen:
  1. Multi-option quotation → Let customer choose variant
  2. From BOM → Sales picks the variant, sends clean proposal