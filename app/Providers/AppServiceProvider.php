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
// Inventory Domain Services
use App\Services\Sales\DownPaymentService;
use App\Services\Sales\InvoiceService;
// Projects Domain Services
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
        $this->app->bind(\App\Contracts\Sales\QuotationCalculatorInterface::class, \App\Domain\Sales\Quotations\QuotationCalculator::class);

        // Purchasing Domain (4 services)
        $this->app->bind(BillServiceInterface::class, BillService::class);
        $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);
        $this->app->bind(GoodsReceiptNoteServiceInterface::class, GoodsReceiptNoteService::class);
        $this->app->bind(PurchaseReturnServiceInterface::class, PurchaseReturnService::class);
        $this->app->bind(\App\Contracts\Purchasing\PurchaseOrderCalculatorInterface::class, \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderCalculator::class);

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

        // Inventory Domain (3 services)
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->bind(StockOpnameServiceInterface::class, StockOpnameService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);

        // Projects Domain (1 service)
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);

        // Solar Domain (2 services)
        $this->app->bind(SolarProposalServiceInterface::class, SolarProposalService::class);
        $this->app->bind(SolarCalculationServiceInterface::class, SolarCalculationService::class);

        // Shared Domain (1 service)
        $this->app->bind(\App\Contracts\Shared\PaymentServiceInterface::class, \App\Services\Shared\PaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        $this->configureRateLimiting();

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
}
