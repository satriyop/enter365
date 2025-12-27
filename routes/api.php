<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\BomController;
use App\Http\Controllers\Api\V1\BomVariantGroupController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\ComponentBrandMappingController;
use App\Http\Controllers\Api\V1\ComponentCrossReferenceController;
use App\Http\Controllers\Api\V1\ComponentStandardController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryOrderController;
use App\Http\Controllers\Api\V1\DownPaymentController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\FiscalPeriodController;
use App\Http\Controllers\Api\V1\GoodsReceiptNoteController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\MaterialRequisitionController;
use App\Http\Controllers\Api\V1\MrpController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseReturnController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\QuotationFollowUpController;
use App\Http\Controllers\Api\V1\RecurringTemplateController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SalesReturnController;
use App\Http\Controllers\Api\V1\SolarDataController;
use App\Http\Controllers\Api\V1\SolarProposalController;
use App\Http\Controllers\Api\V1\StockOpnameController;
use App\Http\Controllers\Api\V1\SubcontractorInvoiceController;
use App\Http\Controllers\Api\V1\SubcontractorWorkOrderController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Indonesian Accounting System
|--------------------------------------------------------------------------
|
| Simple Accounting System API for Indonesian SMEs
| Following SAK EMKM (Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah)
|
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Requires Authentication)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Authentication Routes (Authenticated)
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        });

        // Feature Flags Status
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');

        // User Management (Admin only for create/delete, users can update themselves)
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/password', [UserController::class, 'updatePassword']);
        Route::post('users/{user}/roles', [UserController::class, 'assignRoles']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive']);

        // Chart of Accounts (Bagan Akun)
        Route::apiResource('accounts', AccountController::class);
        Route::get('accounts/{account}/balance', [AccountController::class, 'balance']);
        Route::get('accounts/{account}/ledger', [AccountController::class, 'ledger']);

        // Contacts (Pelanggan & Supplier)
        Route::apiResource('contacts', ContactController::class);
        Route::get('contacts/{contact}/balances', [ContactController::class, 'balances']);
        Route::get('contacts/{contact}/credit-status', [ContactController::class, 'creditStatus']);

        // Product Categories (Kategori Produk)
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::get('product-categories-tree', [ProductCategoryController::class, 'tree']);

        // Products (Produk & Jasa)
        Route::apiResource('products', ProductController::class);
        Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate']);
        Route::get('products-low-stock', [ProductController::class, 'lowStock']);
        Route::get('products-price-list', [ProductController::class, 'priceList']);
        Route::get('products-lookup', [ProductController::class, 'lookup']);

        // Warehouses (Gudang)
        Route::middleware('feature:warehouses')->group(function () {
            Route::apiResource('warehouses', WarehouseController::class);
            Route::post('warehouses/{warehouse}/set-default', [WarehouseController::class, 'setDefault']);
            Route::get('warehouses/{warehouse}/stock-summary', [WarehouseController::class, 'stockSummary']);
        });

        // Inventory (Inventori)
        Route::middleware('feature:inventory')->prefix('inventory')->group(function () {
            Route::get('movements', [InventoryController::class, 'movements']);
            Route::post('stock-in', [InventoryController::class, 'stockIn']);
            Route::post('stock-out', [InventoryController::class, 'stockOut']);
            Route::post('adjust', [InventoryController::class, 'adjust']);
            Route::post('transfer', [InventoryController::class, 'transfer']);
            Route::get('stock-card/{product}', [InventoryController::class, 'stockCard']);
            Route::get('valuation', [InventoryController::class, 'valuation']);
            Route::get('summary', [InventoryController::class, 'summary']);
            Route::get('movement-summary', [InventoryController::class, 'movementSummary']);
            Route::get('stock-levels', [InventoryController::class, 'stockLevels']);
        });

        // Journal Entries (Jurnal Umum)
        Route::get('journal-entries', [JournalEntryController::class, 'index']);
        Route::post('journal-entries', [JournalEntryController::class, 'store']);
        Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show']);
        Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post']);
        Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse']);

        // Quotations (Penawaran)
        Route::middleware('feature:quotations')->group(function () {
            Route::apiResource('quotations', QuotationController::class);
            Route::post('quotations/{quotation}/submit', [QuotationController::class, 'submit']);
            Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve']);
            Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject']);
            Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise']);
            Route::post('quotations/{quotation}/convert-to-invoice', [QuotationController::class, 'convertToInvoice']);
            Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate']);
            Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf']);
            Route::get('quotations-statistics', [QuotationController::class, 'statistics']);

            // Create Quotation from BOM
            Route::post('quotations/from-bom', [QuotationController::class, 'fromBom']);

            // Multi-Option Quotation Variant Routes
            Route::get('quotations/{quotation}/variant-options', [QuotationController::class, 'variantOptions']);
            Route::put('quotations/{quotation}/variant-options', [QuotationController::class, 'syncVariantOptions']);
            Route::post('quotations/{quotation}/select-variant', [QuotationController::class, 'selectVariant']);
            Route::get('quotations/{quotation}/variant-comparison', [QuotationController::class, 'variantComparison']);

            // Quotation Follow-Up & Sales Pipeline
            Route::prefix('quotation-follow-up')->group(function () {
                Route::get('/', [QuotationFollowUpController::class, 'index']);
                Route::get('/statistics', [QuotationFollowUpController::class, 'statistics']);
                Route::get('/summary', [QuotationFollowUpController::class, 'followUpSummary']);
            });
            Route::get('quotations/{quotation}/activities', [QuotationFollowUpController::class, 'activities']);
            Route::post('quotations/{quotation}/activities', [QuotationFollowUpController::class, 'storeActivity']);
            Route::post('quotations/{quotation}/schedule-follow-up', [QuotationFollowUpController::class, 'scheduleFollowUp']);
            Route::post('quotations/{quotation}/assign', [QuotationFollowUpController::class, 'assign']);
            Route::post('quotations/{quotation}/priority', [QuotationFollowUpController::class, 'updatePriority']);
            Route::post('quotations/{quotation}/mark-won', [QuotationFollowUpController::class, 'markAsWon']);
            Route::post('quotations/{quotation}/mark-lost', [QuotationFollowUpController::class, 'markAsLost']);
        });

        // Invoices - Sales (Faktur Penjualan)
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/post', [InvoiceController::class, 'post']);
        Route::post('invoices/{invoice}/make-recurring', [InvoiceController::class, 'makeRecurring']);

        // Purchase Orders (Pesanan Pembelian)
        Route::middleware('feature:purchase_orders')->group(function () {
            Route::apiResource('purchase-orders', PurchaseOrderController::class);
            Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit']);
            Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve']);
            Route::post('purchase-orders/{purchase_order}/reject', [PurchaseOrderController::class, 'reject']);
            Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);
            Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive']);
            Route::post('purchase-orders/{purchase_order}/convert-to-bill', [PurchaseOrderController::class, 'convertToBill']);
            Route::post('purchase-orders/{purchase_order}/duplicate', [PurchaseOrderController::class, 'duplicate']);
            Route::get('purchase-orders-outstanding', [PurchaseOrderController::class, 'outstanding']);
            Route::get('purchase-orders-statistics', [PurchaseOrderController::class, 'statistics']);
        });

        // Bills - Purchases (Faktur Pembelian)
        Route::apiResource('bills', BillController::class);
        Route::post('bills/{bill}/post', [BillController::class, 'post']);
        Route::post('bills/{bill}/make-recurring', [BillController::class, 'makeRecurring']);

        // Down Payments (Uang Muka)
        Route::middleware('feature:down_payments')->group(function () {
            Route::apiResource('down-payments', DownPaymentController::class);
            Route::post('down-payments/{down_payment}/apply-to-invoice/{invoice}', [DownPaymentController::class, 'applyToInvoice']);
            Route::post('down-payments/{down_payment}/apply-to-bill/{bill}', [DownPaymentController::class, 'applyToBill']);
            Route::delete('down-payments/{down_payment}/applications/{application}', [DownPaymentController::class, 'unapply']);
            Route::post('down-payments/{down_payment}/refund', [DownPaymentController::class, 'refund']);
            Route::post('down-payments/{down_payment}/cancel', [DownPaymentController::class, 'cancel']);
            Route::get('down-payments/{down_payment}/applications', [DownPaymentController::class, 'applications']);
            Route::get('down-payments-available', [DownPaymentController::class, 'available']);
            Route::get('down-payments-statistics', [DownPaymentController::class, 'statistics']);
        });

        // Delivery Orders (Surat Jalan)
        Route::middleware('feature:delivery_orders')->group(function () {
            Route::apiResource('delivery-orders', DeliveryOrderController::class);
            Route::post('delivery-orders/{delivery_order}/confirm', [DeliveryOrderController::class, 'confirm']);
            Route::post('delivery-orders/{delivery_order}/ship', [DeliveryOrderController::class, 'ship']);
            Route::post('delivery-orders/{delivery_order}/deliver', [DeliveryOrderController::class, 'deliver']);
            Route::post('delivery-orders/{delivery_order}/cancel', [DeliveryOrderController::class, 'cancel']);
            Route::post('delivery-orders/{delivery_order}/update-progress', [DeliveryOrderController::class, 'updateProgress']);
            Route::post('delivery-orders/{delivery_order}/duplicate', [DeliveryOrderController::class, 'duplicate']);
            Route::post('invoices/{invoice}/create-delivery-order', [DeliveryOrderController::class, 'createFromInvoice']);
            Route::get('invoices/{invoice}/delivery-orders', [DeliveryOrderController::class, 'forInvoice']);
            Route::get('delivery-orders-statistics', [DeliveryOrderController::class, 'statistics']);
        });

        // Sales Returns (Retur Penjualan)
        Route::middleware('feature:sales_returns')->group(function () {
            Route::apiResource('sales-returns', SalesReturnController::class);
            Route::post('sales-returns/{sales_return}/submit', [SalesReturnController::class, 'submit']);
            Route::post('sales-returns/{sales_return}/approve', [SalesReturnController::class, 'approve']);
            Route::post('sales-returns/{sales_return}/reject', [SalesReturnController::class, 'reject']);
            Route::post('sales-returns/{sales_return}/complete', [SalesReturnController::class, 'complete']);
            Route::post('sales-returns/{sales_return}/cancel', [SalesReturnController::class, 'cancel']);
            Route::post('invoices/{invoice}/create-sales-return', [SalesReturnController::class, 'createFromInvoice']);
            Route::get('invoices/{invoice}/sales-returns', [SalesReturnController::class, 'forInvoice']);
            Route::get('sales-returns-statistics', [SalesReturnController::class, 'statistics']);
        });

        // Purchase Returns (Retur Pembelian)
        Route::middleware('feature:purchase_returns')->group(function () {
            Route::apiResource('purchase-returns', PurchaseReturnController::class);
            Route::post('purchase-returns/{purchase_return}/submit', [PurchaseReturnController::class, 'submit']);
            Route::post('purchase-returns/{purchase_return}/approve', [PurchaseReturnController::class, 'approve']);
            Route::post('purchase-returns/{purchase_return}/reject', [PurchaseReturnController::class, 'reject']);
            Route::post('purchase-returns/{purchase_return}/complete', [PurchaseReturnController::class, 'complete']);
            Route::post('purchase-returns/{purchase_return}/cancel', [PurchaseReturnController::class, 'cancel']);
            Route::post('bills/{bill}/create-purchase-return', [PurchaseReturnController::class, 'createFromBill']);
            Route::get('bills/{bill}/purchase-returns', [PurchaseReturnController::class, 'forBill']);
            Route::get('purchase-returns-statistics', [PurchaseReturnController::class, 'statistics']);
        });

        // Bill of Materials (BOM)
        Route::middleware('feature:bom')->group(function () {
            Route::apiResource('boms', BomController::class);
            Route::post('boms/{bom}/activate', [BomController::class, 'activate']);
            Route::post('boms/{bom}/deactivate', [BomController::class, 'deactivate']);
            Route::post('boms/{bom}/duplicate', [BomController::class, 'duplicate']);
            Route::get('boms-for-product/{product}', [BomController::class, 'forProduct']);
            Route::post('boms-calculate-cost', [BomController::class, 'calculateCost']);
            Route::get('boms-statistics', [BomController::class, 'statistics']);

            // BOM Variant Groups (Multi-BOM Comparison)
            Route::apiResource('bom-variant-groups', BomVariantGroupController::class);
            Route::get('bom-variant-groups/{bom_variant_group}/compare', [BomVariantGroupController::class, 'compare']);
            Route::get('bom-variant-groups/{bom_variant_group}/compare-detailed', [BomVariantGroupController::class, 'compareDetailed']);
            Route::post('bom-variant-groups/{bom_variant_group}/boms', [BomVariantGroupController::class, 'addBom']);
            Route::delete('bom-variant-groups/{bom_variant_group}/boms/{bom}', [BomVariantGroupController::class, 'removeBom']);
            Route::post('bom-variant-groups/{bom_variant_group}/boms/{bom}/set-primary', [BomVariantGroupController::class, 'setPrimary']);
            Route::post('bom-variant-groups/{bom_variant_group}/reorder', [BomVariantGroupController::class, 'reorder']);
            Route::post('bom-variant-groups/{bom_variant_group}/create-variant', [BomVariantGroupController::class, 'createVariant']);

            // Component Cross-Reference (Brand Equivalents)
            Route::prefix('component-standards')->group(function () {
                Route::get('/', [ComponentStandardController::class, 'index']);
                Route::post('/', [ComponentStandardController::class, 'store']);
                Route::get('/categories', [ComponentStandardController::class, 'categories']);
                Route::get('/{componentStandard}', [ComponentStandardController::class, 'show']);
                Route::put('/{componentStandard}', [ComponentStandardController::class, 'update']);
                Route::delete('/{componentStandard}', [ComponentStandardController::class, 'destroy']);
                Route::get('/{componentStandard}/brands', [ComponentStandardController::class, 'brands']);

                // Brand Mappings
                Route::post('/{componentStandard}/mappings', [ComponentBrandMappingController::class, 'store']);
                Route::put('/{componentStandard}/mappings/{mapping}', [ComponentBrandMappingController::class, 'update']);
                Route::delete('/{componentStandard}/mappings/{mapping}', [ComponentBrandMappingController::class, 'destroy']);
                Route::post('/{componentStandard}/mappings/{mapping}/verify', [ComponentBrandMappingController::class, 'verify']);
                Route::post('/{componentStandard}/mappings/{mapping}/set-preferred', [ComponentBrandMappingController::class, 'setPreferred']);
            });

            // Cross-Reference Queries
            Route::get('products/{product}/equivalents', [ComponentCrossReferenceController::class, 'productEquivalents']);
            Route::get('component-search', [ComponentCrossReferenceController::class, 'search']);
            Route::get('available-brands', [ComponentCrossReferenceController::class, 'availableBrands']);

            // BOM Brand Swap
            Route::post('boms/{bom}/swap-brand', [ComponentCrossReferenceController::class, 'swapBrand']);
            Route::post('boms/{bom}/generate-brand-variants', [ComponentCrossReferenceController::class, 'generateBrandVariants']);
        });

        // Solar Proposals (Proposal Panel Surya)
        Route::middleware('feature:solar_proposals')->group(function () {
            Route::apiResource('solar-proposals', SolarProposalController::class)
                ->parameters(['solar-proposals' => 'solarProposal']);
            Route::post('solar-proposals/{solarProposal}/calculate', [SolarProposalController::class, 'calculate']);
            Route::post('solar-proposals/{solarProposal}/attach-variants', [SolarProposalController::class, 'attachVariants']);
            Route::post('solar-proposals/{solarProposal}/select-bom', [SolarProposalController::class, 'selectBom']);
            Route::post('solar-proposals/{solarProposal}/send', [SolarProposalController::class, 'send']);
            Route::post('solar-proposals/{solarProposal}/accept', [SolarProposalController::class, 'accept']);
            Route::post('solar-proposals/{solarProposal}/reject', [SolarProposalController::class, 'reject']);
            Route::post('solar-proposals/{solarProposal}/convert-to-quotation', [SolarProposalController::class, 'convertToQuotation']);
            Route::get('solar-proposals-statistics', [SolarProposalController::class, 'statistics']);

            // Solar Data Lookup
            Route::prefix('solar-data')->group(function () {
                Route::get('lookup', [SolarDataController::class, 'lookup']);
                Route::get('provinces', [SolarDataController::class, 'provinces']);
                Route::get('cities', [SolarDataController::class, 'cities']);
                Route::get('locations', [SolarDataController::class, 'locations']);
            });

            // PLN Tariff Lookup
            Route::prefix('pln-tariffs')->group(function () {
                Route::get('/', [SolarDataController::class, 'tariffs']);
                Route::get('grouped', [SolarDataController::class, 'tariffsGrouped']);
                Route::get('{code}', [SolarDataController::class, 'tariffByCode']);
            });
        });

        // Projects (Proyek)
        Route::middleware('feature:projects')->group(function () {
            Route::apiResource('projects', ProjectController::class);
            Route::post('quotations/{quotation}/create-project', [ProjectController::class, 'createFromQuotation']);
            Route::post('projects/{project}/start', [ProjectController::class, 'start']);
            Route::post('projects/{project}/hold', [ProjectController::class, 'hold']);
            Route::post('projects/{project}/resume', [ProjectController::class, 'resume']);
            Route::post('projects/{project}/complete', [ProjectController::class, 'complete']);
            Route::post('projects/{project}/cancel', [ProjectController::class, 'cancel']);
            Route::post('projects/{project}/update-progress', [ProjectController::class, 'updateProgress']);
            Route::post('projects/{project}/costs', [ProjectController::class, 'addCost']);
            Route::put('projects/{project}/costs/{cost}', [ProjectController::class, 'updateCost']);
            Route::delete('projects/{project}/costs/{cost}', [ProjectController::class, 'deleteCost']);
            Route::post('projects/{project}/revenues', [ProjectController::class, 'addRevenue']);
            Route::put('projects/{project}/revenues/{revenue}', [ProjectController::class, 'updateRevenue']);
            Route::delete('projects/{project}/revenues/{revenue}', [ProjectController::class, 'deleteRevenue']);
            Route::get('projects/{project}/summary', [ProjectController::class, 'summary']);
            Route::get('projects-statistics', [ProjectController::class, 'statistics']);
        });

        // Work Orders (Perintah Kerja)
        Route::middleware('feature:work_orders')->group(function () {
            Route::apiResource('work-orders', WorkOrderController::class);
            Route::post('projects/{project}/work-orders', [WorkOrderController::class, 'createForProject']);
            Route::post('boms/{bom}/create-work-order', [WorkOrderController::class, 'createFromBom']);
            Route::get('work-orders/{work_order}/sub-work-orders', [WorkOrderController::class, 'subWorkOrders']);
            Route::post('work-orders/{work_order}/sub-work-orders', [WorkOrderController::class, 'createSubWorkOrder']);
            Route::post('work-orders/{work_order}/confirm', [WorkOrderController::class, 'confirm']);
            Route::post('work-orders/{work_order}/start', [WorkOrderController::class, 'start']);
            Route::post('work-orders/{work_order}/complete', [WorkOrderController::class, 'complete']);
            Route::post('work-orders/{work_order}/cancel', [WorkOrderController::class, 'cancel']);
            Route::post('work-orders/{work_order}/record-output', [WorkOrderController::class, 'recordOutput']);
            Route::post('work-orders/{work_order}/record-consumption', [WorkOrderController::class, 'recordConsumption']);
            Route::get('work-orders/{work_order}/cost-summary', [WorkOrderController::class, 'costSummary']);
            Route::get('work-orders/{work_order}/material-status', [WorkOrderController::class, 'materialStatus']);
            Route::get('work-orders-statistics', [WorkOrderController::class, 'statistics']);
        });

        // Material Requisitions (Permintaan Material)
        Route::middleware('feature:material_requisitions')->group(function () {
            Route::get('material-requisitions', [MaterialRequisitionController::class, 'index']);
            Route::post('work-orders/{work_order}/material-requisitions', [MaterialRequisitionController::class, 'createForWorkOrder']);
            Route::get('material-requisitions/{material_requisition}', [MaterialRequisitionController::class, 'show']);
            Route::put('material-requisitions/{material_requisition}', [MaterialRequisitionController::class, 'update']);
            Route::delete('material-requisitions/{material_requisition}', [MaterialRequisitionController::class, 'destroy']);
            Route::post('material-requisitions/{material_requisition}/approve', [MaterialRequisitionController::class, 'approve']);
            Route::post('material-requisitions/{material_requisition}/issue', [MaterialRequisitionController::class, 'issue']);
            Route::post('material-requisitions/{material_requisition}/cancel', [MaterialRequisitionController::class, 'cancel']);
        });

        // Payments (Pembayaran)
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('payments', [PaymentController::class, 'store']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::post('payments/{payment}/void', [PaymentController::class, 'void']);

        // Recurring Templates (Template Berulang)
        Route::middleware('feature:recurring')->group(function () {
            Route::apiResource('recurring-templates', RecurringTemplateController::class);
            Route::post('recurring-templates/{recurring_template}/generate', [RecurringTemplateController::class, 'generate']);
            Route::post('recurring-templates/{recurring_template}/pause', [RecurringTemplateController::class, 'pause']);
            Route::post('recurring-templates/{recurring_template}/resume', [RecurringTemplateController::class, 'resume']);
        });

        // Fiscal Periods (Periode Fiskal)
        Route::apiResource('fiscal-periods', FiscalPeriodController::class)->only(['index', 'show', 'store']);
        Route::post('fiscal-periods/{fiscal_period}/lock', [FiscalPeriodController::class, 'lock']);
        Route::post('fiscal-periods/{fiscal_period}/unlock', [FiscalPeriodController::class, 'unlock']);
        Route::post('fiscal-periods/{fiscal_period}/close', [FiscalPeriodController::class, 'close']);
        Route::post('fiscal-periods/{fiscal_period}/reopen', [FiscalPeriodController::class, 'reopen']);
        Route::get('fiscal-periods/{fiscal_period}/closing-checklist', [FiscalPeriodController::class, 'closingChecklist']);

        // Budgets (Anggaran)
        Route::middleware('feature:budgeting')->group(function () {
            Route::apiResource('budgets', BudgetController::class);
            Route::post('budgets/{budget}/lines', [BudgetController::class, 'addLine']);
            Route::put('budgets/{budget}/lines/{line}', [BudgetController::class, 'updateLine']);
            Route::delete('budgets/{budget}/lines/{line}', [BudgetController::class, 'deleteLine']);
            Route::post('budgets/{budget}/approve', [BudgetController::class, 'approve']);
            Route::post('budgets/{budget}/reopen', [BudgetController::class, 'reopen']);
            Route::post('budgets/{budget}/close', [BudgetController::class, 'close']);
            Route::post('budgets/{budget}/copy', [BudgetController::class, 'copy']);
            Route::get('budgets/{budget}/comparison', [BudgetController::class, 'comparison']);
            Route::get('budgets/{budget}/monthly-breakdown', [BudgetController::class, 'monthlyBreakdown']);
            Route::get('budgets/{budget}/summary', [BudgetController::class, 'summary']);
            Route::get('budgets/{budget}/over-budget', [BudgetController::class, 'overBudget']);
        });

        // Bank Reconciliation (Rekonsiliasi Bank)
        Route::middleware('feature:bank_reconciliation')->prefix('bank-transactions')->group(function () {
            Route::get('/', [BankReconciliationController::class, 'index']);
            Route::post('/', [BankReconciliationController::class, 'store']);
            Route::get('/summary', [BankReconciliationController::class, 'summary']);
            Route::post('/bulk-reconcile', [BankReconciliationController::class, 'bulkReconcile']);
            Route::get('/{bank_transaction}', [BankReconciliationController::class, 'show']);
            Route::delete('/{bank_transaction}', [BankReconciliationController::class, 'destroy']);
            Route::post('/{bank_transaction}/match-payment/{payment}', [BankReconciliationController::class, 'matchToPayment']);
            Route::post('/{bank_transaction}/unmatch', [BankReconciliationController::class, 'unmatch']);
            Route::post('/{bank_transaction}/reconcile', [BankReconciliationController::class, 'reconcile']);
            Route::get('/{bank_transaction}/suggest-matches', [BankReconciliationController::class, 'suggestMatches']);
        });

        // Attachments (Lampiran)
        Route::prefix('attachments')->group(function () {
            Route::get('/', [AttachmentController::class, 'index']);
            Route::post('/', [AttachmentController::class, 'store']);
            Route::get('/categories', [AttachmentController::class, 'categories']);
            Route::get('/{attachment}', [AttachmentController::class, 'show']);
            Route::delete('/{attachment}', [AttachmentController::class, 'destroy']);
            Route::get('/{attachment}/download', [AttachmentController::class, 'download'])->name('api.v1.attachments.download');
            Route::get('/for/{type}/{id}', [AttachmentController::class, 'forModel']);
        });

        // Dashboard (Dasbor)
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/receivables', [DashboardController::class, 'receivables']);
            Route::get('/payables', [DashboardController::class, 'payables']);
            Route::get('/cash-flow', [DashboardController::class, 'cashFlow']);
            Route::get('/profit-loss', [DashboardController::class, 'profitLoss']);
            Route::get('/kpis', [DashboardController::class, 'kpis']);
        });

        // Financial Reports (Laporan Keuangan)
        Route::prefix('reports')->group(function () {
            // Basic Reports
            Route::get('trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('income-statement', [ReportController::class, 'incomeStatement']);
            Route::get('general-ledger', [ReportController::class, 'generalLedger']);

            // Aging Reports
            Route::get('receivable-aging', [ReportController::class, 'receivableAging']);
            Route::get('payable-aging', [ReportController::class, 'payableAging']);
            Route::get('contacts/{contact}/aging', [ReportController::class, 'contactAging']);

            // Tax Reports (PPN)
            Route::get('ppn-summary', [ReportController::class, 'ppnSummary']);
            Route::get('ppn-monthly', [ReportController::class, 'ppnMonthly']);
            Route::get('tax-invoice-list', [ReportController::class, 'taxInvoiceList']);
            Route::get('input-tax-list', [ReportController::class, 'inputTaxList']);

            // Cash Flow Reports
            Route::get('cash-flow', [ReportController::class, 'cashFlow']);
            Route::get('daily-cash-movement', [ReportController::class, 'dailyCashMovement']);

            // Project Reports
            Route::get('project-profitability', [ReportController::class, 'projectProfitability']);
            Route::get('projects/{project}/profitability', [ReportController::class, 'projectProfitabilityDetail']);
            Route::get('project-cost-analysis', [ReportController::class, 'projectCostAnalysis']);

            // Work Order Reports
            Route::get('work-order-costs', [ReportController::class, 'workOrderCosts']);
            Route::get('work-orders/{workOrder}/costs', [ReportController::class, 'workOrderCostDetail']);
            Route::get('cost-variance', [ReportController::class, 'costVariance']);

            // Subcontractor Reports
            Route::get('subcontractor-summary', [ReportController::class, 'subcontractorSummary']);
            Route::get('subcontractors/{contact}/summary', [ReportController::class, 'subcontractorDetail']);
            Route::get('subcontractor-retention', [ReportController::class, 'subcontractorRetention']);

            // Equity Reports
            Route::get('changes-in-equity', [ReportController::class, 'changesInEquity']);

            // Bank Reconciliation Reports
            Route::get('accounts/{account}/bank-reconciliation', [ReportController::class, 'bankReconciliation']);
            Route::get('accounts/{account}/bank-reconciliation/outstanding', [ReportController::class, 'bankReconciliationOutstanding']);

            // COGS Reports (Harga Pokok Penjualan)
            Route::get('cogs-summary', [ReportController::class, 'cogsSummary']);
            Route::get('cogs-by-product', [ReportController::class, 'cogsByProduct']);
            Route::get('cogs-by-category', [ReportController::class, 'cogsByCategory']);
            Route::get('cogs-monthly-trend', [ReportController::class, 'cogsMonthlyTrend']);
            Route::get('products/{product}/cogs', [ReportController::class, 'productCOGSDetail']);
        });

        // Export Reports (Ekspor Laporan)
        Route::prefix('export')->group(function () {
            Route::get('trial-balance', [ExportController::class, 'trialBalance']);
            Route::get('balance-sheet', [ExportController::class, 'balanceSheet']);
            Route::get('income-statement', [ExportController::class, 'incomeStatement']);
            Route::get('general-ledger', [ExportController::class, 'generalLedger']);
            Route::get('receivable-aging', [ExportController::class, 'receivableAging']);
            Route::get('payable-aging', [ExportController::class, 'payableAging']);
            Route::get('invoices', [ExportController::class, 'invoices']);
            Route::get('bills', [ExportController::class, 'bills']);
            Route::get('tax-report', [ExportController::class, 'taxReport']);
        });

        // Roles & Permissions (Peran & Hak Akses)
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);
        Route::get('roles/{role}/users', [RoleController::class, 'users']);

        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/grouped', [PermissionController::class, 'grouped']);
        Route::get('permissions/groups', [PermissionController::class, 'groups']);
        Route::get('permissions/{permission}', [PermissionController::class, 'show']);

        // MRP (Material Requirements Planning)
        Route::middleware('feature:mrp')->group(function () {
            Route::apiResource('mrp-runs', MrpController::class)->parameters(['mrp-runs' => 'mrpRun']);
            Route::post('mrp-runs/{mrpRun}/execute', [MrpController::class, 'execute']);
            Route::get('mrp-runs/{mrpRun}/demands', [MrpController::class, 'demands']);
            Route::get('mrp-runs/{mrpRun}/suggestions', [MrpController::class, 'suggestions']);
            Route::post('mrp-suggestions/{suggestion}/accept', [MrpController::class, 'acceptSuggestion']);
            Route::post('mrp-suggestions/{suggestion}/reject', [MrpController::class, 'rejectSuggestion']);
            Route::put('mrp-suggestions/{suggestion}', [MrpController::class, 'updateSuggestion']);
            Route::post('mrp-suggestions/{suggestion}/convert-to-po', [MrpController::class, 'convertToPurchaseOrder']);
            Route::post('mrp-suggestions/{suggestion}/convert-to-wo', [MrpController::class, 'convertToWorkOrder']);
            Route::post('mrp-suggestions/{suggestion}/convert-to-sc-wo', [MrpController::class, 'convertToSubcontractorWorkOrder']);
            Route::post('mrp-suggestions/bulk-accept', [MrpController::class, 'bulkAccept']);
            Route::post('mrp-suggestions/bulk-reject', [MrpController::class, 'bulkReject']);
            Route::get('mrp/shortage-report', [MrpController::class, 'shortageReport']);
            Route::get('mrp/statistics', [MrpController::class, 'statistics']);
        });

        // Subcontractor Work Orders (Perintah Kerja Subkontraktor)
        Route::middleware('feature:subcontracting')->group(function () {
            Route::apiResource('subcontractor-work-orders', SubcontractorWorkOrderController::class)
                ->parameters(['subcontractor-work-orders' => 'subcontractorWorkOrder']);
            Route::post('subcontractor-work-orders/{subcontractorWorkOrder}/assign', [SubcontractorWorkOrderController::class, 'assign']);
            Route::post('subcontractor-work-orders/{subcontractorWorkOrder}/start', [SubcontractorWorkOrderController::class, 'start']);
            Route::post('subcontractor-work-orders/{subcontractorWorkOrder}/update-progress', [SubcontractorWorkOrderController::class, 'updateProgress']);
            Route::post('subcontractor-work-orders/{subcontractorWorkOrder}/complete', [SubcontractorWorkOrderController::class, 'complete']);
            Route::post('subcontractor-work-orders/{subcontractorWorkOrder}/cancel', [SubcontractorWorkOrderController::class, 'cancel']);
            Route::post('subcontractor-work-orders/{subcontractorWorkOrder}/invoices', [SubcontractorWorkOrderController::class, 'createInvoice']);
            Route::get('subcontractor-work-orders/{subcontractorWorkOrder}/invoices', [SubcontractorWorkOrderController::class, 'invoices']);
            Route::get('subcontractors', [SubcontractorWorkOrderController::class, 'subcontractors']);
            Route::get('subcontractor-work-orders-statistics', [SubcontractorWorkOrderController::class, 'statistics']);

            // Subcontractor Invoices (Invoice Subkontraktor)
            Route::get('subcontractor-invoices', [SubcontractorInvoiceController::class, 'index']);
            Route::get('subcontractor-invoices/{subcontractorInvoice}', [SubcontractorInvoiceController::class, 'show']);
            Route::put('subcontractor-invoices/{subcontractorInvoice}', [SubcontractorInvoiceController::class, 'update']);
            Route::post('subcontractor-invoices/{subcontractorInvoice}/approve', [SubcontractorInvoiceController::class, 'approve']);
            Route::post('subcontractor-invoices/{subcontractorInvoice}/reject', [SubcontractorInvoiceController::class, 'reject']);
            Route::post('subcontractor-invoices/{subcontractorInvoice}/convert-to-bill', [SubcontractorInvoiceController::class, 'convertToBill']);
        });

        // Stock Opname (Stock Opname / Physical Inventory)
        Route::middleware('feature:stock_opname')->group(function () {
            Route::apiResource('stock-opnames', StockOpnameController::class)
                ->parameters(['stock-opnames' => 'stockOpname']);
            Route::post('stock-opnames/{stockOpname}/generate-items', [StockOpnameController::class, 'generateItems']);
            Route::post('stock-opnames/{stockOpname}/items', [StockOpnameController::class, 'addItem']);
            Route::put('stock-opnames/{stockOpname}/items/{item}', [StockOpnameController::class, 'updateItem']);
            Route::delete('stock-opnames/{stockOpname}/items/{item}', [StockOpnameController::class, 'removeItem']);
            Route::post('stock-opnames/{stockOpname}/start-counting', [StockOpnameController::class, 'startCounting']);
            Route::post('stock-opnames/{stockOpname}/submit-review', [StockOpnameController::class, 'submitForReview']);
            Route::post('stock-opnames/{stockOpname}/approve', [StockOpnameController::class, 'approve']);
            Route::post('stock-opnames/{stockOpname}/reject', [StockOpnameController::class, 'reject']);
            Route::post('stock-opnames/{stockOpname}/cancel', [StockOpnameController::class, 'cancel']);
            Route::get('stock-opnames/{stockOpname}/variance-report', [StockOpnameController::class, 'varianceReport']);
        });

        // Goods Receipt Notes (Surat Penerimaan Barang)
        Route::middleware('feature:goods_receipt_notes')->group(function () {
            Route::apiResource('goods-receipt-notes', GoodsReceiptNoteController::class)
                ->parameters(['goods-receipt-notes' => 'goodsReceiptNote']);
            Route::post('purchase-orders/{purchaseOrder}/create-grn', [GoodsReceiptNoteController::class, 'createFromPurchaseOrder']);
            Route::put('goods-receipt-notes/{goodsReceiptNote}/items/{item}', [GoodsReceiptNoteController::class, 'updateItem']);
            Route::post('goods-receipt-notes/{goodsReceiptNote}/start-receiving', [GoodsReceiptNoteController::class, 'startReceiving']);
            Route::post('goods-receipt-notes/{goodsReceiptNote}/complete', [GoodsReceiptNoteController::class, 'complete']);
            Route::post('goods-receipt-notes/{goodsReceiptNote}/cancel', [GoodsReceiptNoteController::class, 'cancel']);
            Route::get('purchase-orders/{purchaseOrder}/goods-receipt-notes', [GoodsReceiptNoteController::class, 'forPurchaseOrder']);
        });

    }); // End of auth:sanctum middleware group

}); // End of v1 prefix group
