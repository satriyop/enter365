<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\FiscalPeriodController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RecurringTemplateController;
use App\Http\Controllers\Api\V1\ReportController;
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

    // Journal Entries (Jurnal Umum)
    Route::get('journal-entries', [JournalEntryController::class, 'index']);
    Route::post('journal-entries', [JournalEntryController::class, 'store']);
    Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show']);
    Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post']);
    Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse']);

    // Invoices - Sales (Faktur Penjualan)
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/post', [InvoiceController::class, 'post']);
    Route::post('invoices/{invoice}/make-recurring', [InvoiceController::class, 'makeRecurring']);

    // Bills - Purchases (Faktur Pembelian)
    Route::apiResource('bills', BillController::class);
    Route::post('bills/{bill}/post', [BillController::class, 'post']);
    Route::post('bills/{bill}/make-recurring', [BillController::class, 'makeRecurring']);

    // Payments (Pembayaran)
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::post('payments/{payment}/void', [PaymentController::class, 'void']);

    // Recurring Templates (Template Berulang)
    Route::apiResource('recurring-templates', RecurringTemplateController::class);
    Route::post('recurring-templates/{recurring_template}/generate', [RecurringTemplateController::class, 'generate']);
    Route::post('recurring-templates/{recurring_template}/pause', [RecurringTemplateController::class, 'pause']);
    Route::post('recurring-templates/{recurring_template}/resume', [RecurringTemplateController::class, 'resume']);

    // Fiscal Periods (Periode Fiskal)
    Route::apiResource('fiscal-periods', FiscalPeriodController::class)->only(['index', 'show', 'store']);
    Route::post('fiscal-periods/{fiscal_period}/lock', [FiscalPeriodController::class, 'lock']);
    Route::post('fiscal-periods/{fiscal_period}/unlock', [FiscalPeriodController::class, 'unlock']);
    Route::post('fiscal-periods/{fiscal_period}/close', [FiscalPeriodController::class, 'close']);
    Route::post('fiscal-periods/{fiscal_period}/reopen', [FiscalPeriodController::class, 'reopen']);
    Route::get('fiscal-periods/{fiscal_period}/closing-checklist', [FiscalPeriodController::class, 'closingChecklist']);

    // Bank Reconciliation (Rekonsiliasi Bank)
    Route::prefix('bank-transactions')->group(function () {
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
});
