<?php

namespace App\Providers;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Contracts\FeatureManager;
// Sales Domain Interfaces
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Inventory\ProductServiceInterface;
use App\Contracts\Inventory\StockOpnameServiceInterface;
use App\Contracts\Manufacturing\BomServiceInterface;
use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Contracts\Manufacturing\BomVariantGroupServiceInterface;
// Purchasing Domain Interfaces
use App\Contracts\Manufacturing\MaterialRequisitionServiceInterface;
use App\Contracts\Manufacturing\MrpServiceInterface;
use App\Contracts\Manufacturing\SubcontractorServiceInterface;
use App\Contracts\Manufacturing\WorkOrderServiceInterface;
// Manufacturing Domain Interfaces
use App\Contracts\Projects\ProjectServiceInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Purchasing\GoodsReceiptNoteServiceInterface;
use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Contracts\Purchasing\PurchaseReturnServiceInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Contracts\Sales\DownPaymentServiceInterface;
// Inventory Domain Interfaces
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Contracts\Sales\RecurringServiceInterface;
// Projects Domain Interfaces
use App\Contracts\Sales\SalesReturnServiceInterface;
// Solar Domain Interfaces
use App\Contracts\Solar\SolarCalculationServiceInterface;
use App\Contracts\Solar\SolarProposalServiceInterface;
// Sales Domain Services
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Accounting\JournalService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProductService;
use App\Services\Inventory\StockOpnameService;
use App\Services\Manufacturing\BomService;
use App\Services\Manufacturing\BomTemplateService;
// Purchasing Domain Services
use App\Services\Manufacturing\BomVariantGroupService;
use App\Services\Manufacturing\MaterialRequisitionService;
use App\Services\Manufacturing\MrpService;
use App\Services\Manufacturing\SubcontractorService;
// Manufacturing Domain Services
use App\Services\Manufacturing\WorkOrderService;
use App\Services\Projects\ProjectService;
use App\Services\Purchasing\BillService;
use App\Services\Purchasing\GoodsReceiptNoteService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseReturnService;
use App\Services\Sales\DeliveryOrderService;
use App\Services\Sales\DownPaymentService;
// Projects Domain Services
use App\Services\Sales\InvoiceService;
use App\Services\Sales\QuotationService;
// Solar Domain Services
use App\Services\Sales\RecurringService;
use App\Services\Sales\SalesReturnService;
// Accounting Strategies
use App\Services\Solar\SolarCalculationService;
use App\Services\Solar\SolarProposalService;
use App\Support\ConfigFeatureManager;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FeatureManager::class, ConfigFeatureManager::class);

        $this->registerInfrastructureServices();
        $this->registerAccountingServices();
        $this->registerDomainServices();
    }

    /**
     * Register infrastructure services (events, logging, etc.).
     */
    private function registerInfrastructureServices(): void
    {
        $this->app->bind(
            \App\Contracts\Events\EventDispatcherInterface::class,
            \App\Infrastructure\Events\LaravelEventDispatcher::class
        );

        $this->app->singleton(
            \App\Contracts\Logging\ContextualLoggerInterface::class,
            \App\Infrastructure\Logging\LaravelContextualLogger::class
        );
    }

    /**
     * Register accounting services and strategy bindings.
     */
    private function registerAccountingServices(): void
    {
        // Register JournalService
        $this->app->bind(JournalServiceInterface::class, JournalService::class);

        // Register AccountLookupService
        $this->app->bind(
            \App\Contracts\Accounting\AccountLookupServiceInterface::class,
            \App\Services\Accounting\AccountLookupService::class
        );

        // Accounting Domain CRUD services
        $this->app->bind(\App\Contracts\Accounting\AccountServiceInterface::class, \App\Services\Accounting\AccountService::class);
        $this->app->bind(\App\Contracts\Accounting\BudgetServiceInterface::class, \App\Services\Accounting\BudgetService::class);
        $this->app->bind(\App\Contracts\Accounting\FiscalPeriodServiceInterface::class, \App\Services\Accounting\FiscalPeriodService::class);
        $this->app->bind(\App\Contracts\Accounting\BankReconciliationServiceInterface::class, \App\Services\Accounting\BankReconciliationService::class);
        $this->app->bind(\App\Contracts\Accounting\YearEndCloseServiceInterface::class, \App\Services\Accounting\YearEndCloseService::class);

        // Register AccountingPolicyManager as singleton (reads config once)
        $this->app->singleton(AccountingPolicyManager::class);

        // Bind strategy interfaces to resolved strategies from PolicyManager
        $this->app->bind(InventoryAccountingStrategy::class, function ($app) {
            return $app->make(AccountingPolicyManager::class)->inventory();
        });

        $this->app->bind(COGSRecognitionStrategy::class, function ($app) {
            return $app->make(AccountingPolicyManager::class)->cogs();
        });

        $this->app->bind(ReturnAccountingStrategy::class, function ($app) {
            return $app->make(AccountingPolicyManager::class)->returns();
        });

        $this->app->bind(ManufacturingCostStrategy::class, function ($app) {
            return $app->make(AccountingPolicyManager::class)->manufacturing();
        });
    }

    /**
     * Register domain service bindings.
     */
    private function registerDomainServices(): void
    {
        // Sales Domain (6 services)
        $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
        $this->app->bind(QuotationServiceInterface::class, QuotationService::class);
        $this->app->bind(DownPaymentServiceInterface::class, DownPaymentService::class);
        $this->app->bind(DeliveryOrderServiceInterface::class, DeliveryOrderService::class);
        $this->app->bind(SalesReturnServiceInterface::class, SalesReturnService::class);

        // Register SalesReturnApprovalPipeline with handlers
        $this->app->bind(
            \App\Domain\Sales\SalesReturns\Handlers\SalesReturnApprovalPipeline::class,
            function ($app) {
                $pipeline = new \App\Domain\Sales\SalesReturns\Handlers\SalesReturnApprovalPipeline;
                $pipeline->addHandler($app->make(\App\Domain\Sales\SalesReturns\Handlers\InventoryReturnHandler::class));
                $pipeline->addHandler($app->make(\App\Domain\Sales\SalesReturns\Handlers\JournalEntryHandler::class));

                return $pipeline;
            }
        );

        $this->app->bind(RecurringServiceInterface::class, RecurringService::class);
        $this->app->bind(\App\Contracts\Sales\QuotationFollowUpServiceInterface::class, \App\Services\Sales\QuotationFollowUpService::class);
        $this->app->bind(\App\Contracts\Sales\QuotationCalculatorInterface::class, \App\Domain\Sales\Quotations\QuotationCalculator::class);
        $this->app->bind(\App\Contracts\Sales\QuotationConversionServiceInterface::class, \App\Services\Sales\QuotationConversionService::class);

        // QuotationDomainFactory - singleton since it caches managers
        $this->app->singleton(\App\Domain\Sales\Quotations\QuotationDomainFactory::class);

        // InvoiceCalculator and InvoiceDomainFactory
        $this->app->bind(\App\Contracts\Sales\InvoiceCalculatorInterface::class, \App\Domain\Sales\Invoices\InvoiceCalculator::class);
        $this->app->singleton(\App\Domain\Sales\Invoices\InvoiceDomainFactory::class);

        // Purchasing Domain (4 services)
        $this->app->bind(BillServiceInterface::class, BillService::class);
        $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);
        $this->app->bind(GoodsReceiptNoteServiceInterface::class, GoodsReceiptNoteService::class);
        $this->app->bind(PurchaseReturnServiceInterface::class, PurchaseReturnService::class);
        $this->app->bind(\App\Contracts\Purchasing\PurchaseOrderCalculatorInterface::class, \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderCalculator::class);
        $this->app->singleton(\App\Domain\Purchasing\PurchaseOrders\PurchaseOrderDomainFactory::class);

        // Register PurchaseReturnApprovalPipeline with handlers
        $this->app->bind(
            \App\Domain\Purchasing\PurchaseReturns\Handlers\PurchaseReturnApprovalPipeline::class,
            function ($app) {
                $pipeline = new \App\Domain\Purchasing\PurchaseReturns\Handlers\PurchaseReturnApprovalPipeline;
                $pipeline->addHandler($app->make(\App\Domain\Purchasing\PurchaseReturns\Handlers\InventoryReturnHandler::class));
                $pipeline->addHandler($app->make(\App\Domain\Purchasing\PurchaseReturns\Handlers\JournalEntryHandler::class));

                return $pipeline;
            }
        );

        // Manufacturing Domain (6 services)
        $this->app->bind(BomServiceInterface::class, BomService::class);
        $this->app->bind(BomTemplateServiceInterface::class, BomTemplateService::class);
        $this->app->bind(BomVariantGroupServiceInterface::class, BomVariantGroupService::class);
        $this->app->bind(WorkOrderServiceInterface::class, WorkOrderService::class);
        $this->app->bind(MaterialRequisitionServiceInterface::class, MaterialRequisitionService::class);
        $this->app->bind(MrpServiceInterface::class, MrpService::class);
        $this->app->bind(SubcontractorServiceInterface::class, SubcontractorService::class);

        // WorkOrderDomainFactory
        $this->app->singleton(\App\Domain\Manufacturing\WorkOrders\WorkOrderDomainFactory::class);

        // Register WorkOrderCompletionPipeline with handlers
        $this->app->bind(
            \App\Domain\Manufacturing\WorkOrders\Handlers\WorkOrderCompletionPipeline::class,
            function ($app) {
                $pipeline = new \App\Domain\Manufacturing\WorkOrders\Handlers\WorkOrderCompletionPipeline;
                $pipeline->addHandler($app->make(\App\Domain\Manufacturing\WorkOrders\Handlers\MaterialConsumptionHandler::class));
                $pipeline->addHandler($app->make(\App\Domain\Manufacturing\WorkOrders\Handlers\FinishedGoodsHandler::class));
                $pipeline->addHandler($app->make(\App\Domain\Manufacturing\WorkOrders\Handlers\CostCalculationHandler::class));

                return $pipeline;
            }
        );

        // Manufacturing Cost service
        $this->app->bind(\App\Contracts\Manufacturing\WorkOrderCostServiceInterface::class, \App\Services\Manufacturing\WorkOrderCostService::class);

        // Inventory Domain
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->bind(StockOpnameServiceInterface::class, StockOpnameService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(\App\Contracts\Inventory\WarehouseServiceInterface::class, \App\Services\Inventory\WarehouseService::class);
        $this->app->bind(\App\Contracts\Inventory\ProductCategoryServiceInterface::class, \App\Services\Inventory\ProductCategoryService::class);

        // Projects Domain (1 service)
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);

        // Solar Domain (2 services)
        $this->app->bind(SolarProposalServiceInterface::class, SolarProposalService::class);
        $this->app->bind(SolarCalculationServiceInterface::class, SolarCalculationService::class);

        // Shared Domain
        $this->app->bind(\App\Contracts\Shared\PaymentServiceInterface::class, \App\Services\Shared\PaymentService::class);
        $this->app->bind(\App\Contracts\Shared\ContactServiceInterface::class, \App\Services\Shared\ContactService::class);
        $this->app->bind(\App\Contracts\Shared\ReminderServiceInterface::class, \App\Services\Sales\ReminderService::class);
        $this->app->bind(\App\Contracts\Shared\OverdueServiceInterface::class, \App\Services\Sales\OverdueService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->registerObservers();

        // Events are now handled by EventServiceProvider via subscribers
        // See: app/Providers/EventServiceProvider.php

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                        ->setDescription('Sanctum Bearer Token. Obtain via POST /api/v1/auth/login')
                );
            });
    }

    /**
     * Register model policies for authorization.
     *
     * Models are in domain subdirectories so Laravel can't auto-discover them.
     */
    private function configurePolicies(): void
    {
        Gate::policy(\App\Models\Accounting\Account::class, \App\Policies\AccountPolicy::class);
        Gate::policy(\App\Models\Contacts\Contact::class, \App\Policies\ContactPolicy::class);
        Gate::policy(\App\Models\Inventory\Product::class, \App\Policies\ProductPolicy::class);
        Gate::policy(\App\Models\Sales\Invoice::class, \App\Policies\InvoicePolicy::class);
        Gate::policy(\App\Models\Sales\Quotation::class, \App\Policies\QuotationPolicy::class);
        Gate::policy(\App\Models\Purchasing\Bill::class, \App\Policies\BillPolicy::class);
        Gate::policy(\App\Models\Purchasing\PurchaseOrder::class, \App\Policies\PurchaseOrderPolicy::class);
        Gate::policy(\App\Models\Shared\Payment::class, \App\Policies\PaymentPolicy::class);
        Gate::policy(\App\Models\Accounting\JournalEntry::class, \App\Policies\JournalEntryPolicy::class);
        Gate::policy(\App\Models\Accounting\Budget::class, \App\Policies\BudgetPolicy::class);
        Gate::policy(\App\Models\Sales\DeliveryOrder::class, \App\Policies\DeliveryOrderPolicy::class);
        Gate::policy(\App\Models\Manufacturing\WorkOrder::class, \App\Policies\WorkOrderPolicy::class);
        Gate::policy(\App\Models\Manufacturing\Bom::class, \App\Policies\BomPolicy::class);
        Gate::policy(\App\Models\Projects\Project::class, \App\Policies\ProjectPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\App\Models\Sales\SalesReturn::class, \App\Policies\SalesReturnPolicy::class);
        Gate::policy(\App\Models\Purchasing\GoodsReceiptNote::class, \App\Policies\GoodsReceiptNotePolicy::class);
        Gate::policy(\App\Models\Purchasing\PurchaseReturn::class, \App\Policies\PurchaseReturnPolicy::class);
        Gate::policy(\App\Models\Manufacturing\MaterialRequisition::class, \App\Policies\MaterialRequisitionPolicy::class);
        Gate::policy(\App\Models\Manufacturing\SubcontractorWorkOrder::class, \App\Policies\SubcontractorWorkOrderPolicy::class);
        Gate::policy(\App\Models\Inventory\StockOpname::class, \App\Policies\StockOpnamePolicy::class);
        Gate::policy(\App\Models\Accounting\FiscalPeriod::class, \App\Policies\FiscalPeriodPolicy::class);
        Gate::policy(\App\Models\Inventory\Warehouse::class, \App\Policies\WarehousePolicy::class);
        Gate::policy(\App\Models\Manufacturing\BomVariantGroup::class, \App\Policies\BomVariantGroupPolicy::class);
        Gate::policy(\App\Models\Manufacturing\MrpRun::class, \App\Policies\MrpRunPolicy::class);

        // Dashboard Gates (non-model based)
        Gate::define('dashboard.view', [\App\Policies\DashboardPolicy::class, 'view']);
        Gate::define('dashboard.financials', [\App\Policies\DashboardPolicy::class, 'viewFinancials']);
        Gate::define('dashboard.kpis', [\App\Policies\DashboardPolicy::class, 'viewKpis']);

        // Report Gates (non-model based)
        Gate::define('reports.financial', [\App\Policies\ReportPolicy::class, 'viewFinancial']);
        Gate::define('reports.tax', [\App\Policies\ReportPolicy::class, 'viewTax']);
        Gate::define('reports.aging', [\App\Policies\ReportPolicy::class, 'viewAging']);
        Gate::define('reports.cash_flow', [\App\Policies\ReportPolicy::class, 'viewCashFlow']);
        Gate::define('reports.project', [\App\Policies\ReportPolicy::class, 'viewProject']);
        Gate::define('reports.manufacturing', [\App\Policies\ReportPolicy::class, 'viewManufacturing']);
        Gate::define('reports.cogs', [\App\Policies\ReportPolicy::class, 'viewCogs']);
        Gate::define('reports.export', [\App\Policies\ReportPolicy::class, 'export']);

        // Settings Gates (non-model based)
        Gate::define('settings.features', fn (\App\Models\User $user): bool => $user->hasPermission('settings.features'));

        // User management Gates (non-model based)
        Gate::define('users.manage_roles', fn (\App\Models\User $user): bool => $user->hasPermission('users.manage_roles'));

        // Attachment Policy
        Gate::policy(\App\Models\Shared\Attachment::class, \App\Policies\AttachmentPolicy::class);
    }

    /**
     * Configure API rate limiting.
     *
     * Defines rate limiters for different API endpoint categories:
     * - api: Standard API rate limit (60 req/min)
     * - auth: Strict limit for authentication (5 req/min)
     * - reports: Heavy operations (10 req/min)
     * - exports: File exports (5 req/min)
     */
    private function configureRateLimiting(): void
    {
        // Standard API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Strict rate limit for auth endpoints (login, register, password reset)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Heavy operations (reports, statistics, dashboard)
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Export operations (PDF, Excel, CSV)
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure morph map for polymorphic relationships.
     *
     * This decouples database morph type values from class names,
     * allowing safe namespace reorganization.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            // Sales Domain
            'invoice' => \App\Models\Sales\Invoice::class,
            'invoice_item' => \App\Models\Sales\InvoiceItem::class,
            'quotation' => \App\Models\Sales\Quotation::class,
            'quotation_item' => \App\Models\Sales\QuotationItem::class,
            'sales_return' => \App\Models\Sales\SalesReturn::class,
            'delivery_order' => \App\Models\Sales\DeliveryOrder::class,
            'down_payment' => \App\Models\Sales\DownPayment::class,

            // Purchasing Domain
            'purchase_order' => \App\Models\Purchasing\PurchaseOrder::class,
            'purchase_order_item' => \App\Models\Purchasing\PurchaseOrderItem::class,
            'bill' => \App\Models\Purchasing\Bill::class,
            'bill_item' => \App\Models\Purchasing\BillItem::class,
            'purchase_return' => \App\Models\Purchasing\PurchaseReturn::class,
            'goods_receipt_note' => \App\Models\Purchasing\GoodsReceiptNote::class,

            // Inventory Domain
            'product' => \App\Models\Inventory\Product::class,
            'warehouse' => \App\Models\Inventory\Warehouse::class,
            'inventory_movement' => \App\Models\Inventory\InventoryMovement::class,
            'stock_opname' => \App\Models\Inventory\StockOpname::class,

            // Manufacturing Domain
            'bom' => \App\Models\Manufacturing\Bom::class,
            'bom_item' => \App\Models\Manufacturing\BomItem::class,
            'work_order' => \App\Models\Manufacturing\WorkOrder::class,
            'work_order_item' => \App\Models\Manufacturing\WorkOrderItem::class,
            'material_requisition' => \App\Models\Manufacturing\MaterialRequisition::class,
            'mrp_run' => \App\Models\Manufacturing\MrpRun::class,

            // Accounting Domain
            'journal_entry' => \App\Models\Accounting\JournalEntry::class,
            'account' => \App\Models\Accounting\Account::class,

            // Projects Domain
            'project' => \App\Models\Projects\Project::class,

            // Contacts Domain
            'contact' => \App\Models\Contacts\Contact::class,

            // Solar Domain
            'solar_proposal' => \App\Models\Solar\SolarProposal::class,

            // Shared Domain
            'payment' => \App\Models\Shared\Payment::class,
            'attachment' => \App\Models\Shared\Attachment::class,

            // Core Domain
            'user' => \App\Models\User::class,
        ]);
    }

    /**
     * Register model observers for cache invalidation.
     */
    private function registerObservers(): void
    {
        $observer = \App\Observers\DashboardCacheObserver::class;

        \App\Models\Sales\Invoice::observe($observer);
        \App\Models\Purchasing\Bill::observe($observer);
        \App\Models\Shared\Payment::observe($observer);
        \App\Models\Accounting\JournalEntry::observe($observer);
        \App\Models\Sales\Quotation::observe($observer);
    }
}
