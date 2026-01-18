<?php

namespace App\Providers;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Contracts\FeatureManager;
use App\Contracts\Services\Accounting\JournalServiceInterface;
// Sales Domain Interfaces
use App\Contracts\Services\Domains\BillServiceInterface;
use App\Contracts\Services\Domains\BomServiceInterface;
use App\Contracts\Services\Domains\BomTemplateServiceInterface;
use App\Contracts\Services\Domains\BomVariantGroupServiceInterface;
use App\Contracts\Services\Domains\DeliveryOrderServiceInterface;
use App\Contracts\Services\Domains\DownPaymentServiceInterface;
// Purchasing Domain Interfaces
use App\Contracts\Services\Domains\GoodsReceiptNoteServiceInterface;
use App\Contracts\Services\Domains\InventoryServiceInterface;
use App\Contracts\Services\Domains\InvoiceServiceInterface;
use App\Contracts\Services\Domains\MaterialRequisitionServiceInterface;
// Manufacturing Domain Interfaces
use App\Contracts\Services\Domains\MrpServiceInterface;
use App\Contracts\Services\Domains\ProductServiceInterface;
use App\Contracts\Services\Domains\ProjectServiceInterface;
use App\Contracts\Services\Domains\PurchaseOrderServiceInterface;
use App\Contracts\Services\Domains\PurchaseReturnServiceInterface;
use App\Contracts\Services\Domains\QuotationServiceInterface;
use App\Contracts\Services\Domains\RecurringServiceInterface;
// Inventory Domain Interfaces
use App\Contracts\Services\Domains\SalesReturnServiceInterface;
use App\Contracts\Services\Domains\SolarCalculationServiceInterface;
use App\Contracts\Services\Domains\SolarProposalServiceInterface;
// Projects Domain Interfaces
use App\Contracts\Services\Domains\StockOpnameServiceInterface;
// Solar Domain Interfaces
use App\Contracts\Services\Domains\SubcontractorServiceInterface;
use App\Contracts\Services\Domains\WorkOrderServiceInterface;
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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FeatureManager::class, ConfigFeatureManager::class);

        $this->registerAccountingServices();
        $this->registerDomainServices();
    }

    /**
     * Register accounting services and strategy bindings.
     */
    private function registerAccountingServices(): void
    {
        // Register JournalService
        $this->app->bind(JournalServiceInterface::class, JournalService::class);

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
        $this->app->bind(RecurringServiceInterface::class, RecurringService::class);
        $this->app->bind(\App\Contracts\Services\Sales\QuotationCalculatorInterface::class, \App\Domain\Sales\Quotations\QuotationCalculator::class);

        // Purchasing Domain (4 services)
        $this->app->bind(BillServiceInterface::class, BillService::class);
        $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);
        $this->app->bind(GoodsReceiptNoteServiceInterface::class, GoodsReceiptNoteService::class);
        $this->app->bind(PurchaseReturnServiceInterface::class, PurchaseReturnService::class);
        $this->app->bind(\App\Contracts\Services\Purchasing\PurchaseOrderCalculatorInterface::class, \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderCalculator::class);

        // Manufacturing Domain (6 services)
        $this->app->bind(BomServiceInterface::class, BomService::class);
        $this->app->bind(BomTemplateServiceInterface::class, BomTemplateService::class);
        $this->app->bind(BomVariantGroupServiceInterface::class, BomVariantGroupService::class);
        $this->app->bind(WorkOrderServiceInterface::class, WorkOrderService::class);
        $this->app->bind(MaterialRequisitionServiceInterface::class, MaterialRequisitionService::class);
        $this->app->bind(MrpServiceInterface::class, MrpService::class);
        $this->app->bind(SubcontractorServiceInterface::class, SubcontractorService::class);

        // Inventory Domain (3 services)
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->bind(StockOpnameServiceInterface::class, StockOpnameService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);

        // Projects Domain (1 service)
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);

        // Solar Domain (2 services)
        $this->app->bind(SolarProposalServiceInterface::class, SolarProposalService::class);
        $this->app->bind(SolarCalculationServiceInterface::class, SolarCalculationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        $this->registerEventListeners();

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                        ->setDescription('Sanctum Bearer Token. Obtain via POST /api/v1/auth/login')
                );
            });
    }

    /**
     * Register event listeners for the application.
     */
    private function registerEventListeners(): void
    {
        // Sales Domain Event Listeners
        Event::listen(
            \App\Domain\Sales\Invoices\Events\InvoiceStatusChanged::class,
            \App\Infrastructure\Listeners\Sales\LogInvoiceActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Invoices\Events\InvoiceSent::class,
            \App\Infrastructure\Listeners\Sales\LogInvoiceActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Invoices\Events\InvoiceSent::class,
            \App\Infrastructure\Listeners\Sales\NotifyCustomerOnInvoiceSent::class
        );

        Event::listen(
            \App\Domain\Sales\Invoices\Events\InvoiceVoided::class,
            \App\Infrastructure\Listeners\Sales\LogInvoiceActivity::class
        );

        // Purchasing Domain Event Listeners
        Event::listen(
            \App\Domain\Purchasing\Bills\Events\BillStatusChanged::class,
            \App\Infrastructure\Listeners\Purchasing\LogBillActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\Bills\Events\BillReceived::class,
            \App\Infrastructure\Listeners\Purchasing\LogBillActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\Bills\Events\BillReceived::class,
            \App\Infrastructure\Listeners\Purchasing\NotifyAccountPayableOnBillReceived::class
        );

        Event::listen(
            \App\Domain\Purchasing\Bills\Events\BillVoided::class,
            \App\Infrastructure\Listeners\Purchasing\LogBillActivity::class
        );

        // Sales Domain - Quotation Event Listeners
        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationStatusChanged::class,
            \App\Infrastructure\Listeners\Sales\LogQuotationActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationSubmitted::class,
            \App\Infrastructure\Listeners\Sales\LogQuotationActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationApproved::class,
            \App\Infrastructure\Listeners\Sales\LogQuotationActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationRejected::class,
            \App\Infrastructure\Listeners\Sales\LogQuotationActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationConverted::class,
            \App\Infrastructure\Listeners\Sales\LogQuotationActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationExpired::class,
            \App\Infrastructure\Listeners\Sales\LogQuotationActivity::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationApproved::class,
            \App\Infrastructure\Listeners\Sales\NotifyCustomerOnQuotationApproved::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationSubmitted::class,
            \App\Infrastructure\Listeners\Sales\NotifySalesTeamOnQuotationSubmitted::class
        );

        Event::listen(
            \App\Domain\Sales\Quotations\Events\QuotationWon::class,
            \App\Infrastructure\Listeners\Sales\NotifySalesTeamOnQuotationWon::class
        );

        // Purchasing Domain - Purchase Order Event Listeners
        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderStatusChanged::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderSubmitted::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderApproved::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderRejected::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderCancelled::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderPartial::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        Event::listen(
            \App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderReceived::class,
            \App\Infrastructure\Listeners\Purchasing\LogPurchaseOrderActivity::class
        );

        // Sales Domain - Delivery Order Event Listeners
        Event::listen(
            \App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderStatusChanged::class,
            \App\Infrastructure\Listeners\Sales\LogDeliveryOrderActivity::class
        );

        Event::listen(
            \App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderConfirmed::class,
            \App\Infrastructure\Listeners\Sales\LogDeliveryOrderActivity::class
        );

        Event::listen(
            \App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderShipped::class,
            \App\Infrastructure\Listeners\Sales\LogDeliveryOrderActivity::class
        );

        Event::listen(
            \App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderDelivered::class,
            \App\Infrastructure\Listeners\Sales\LogDeliveryOrderActivity::class
        );

        Event::listen(
            \App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderCancelled::class,
            \App\Infrastructure\Listeners\Sales\LogDeliveryOrderActivity::class
        );

        // Manufacturing Domain - Work Order Event Listeners
        Event::listen(
            \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged::class,
            \App\Infrastructure\Listeners\Manufacturing\LogWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed::class,
            \App\Infrastructure\Listeners\Manufacturing\LogWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted::class,
            \App\Infrastructure\Listeners\Manufacturing\LogWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted::class,
            \App\Infrastructure\Listeners\Manufacturing\LogWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled::class,
            \App\Infrastructure\Listeners\Manufacturing\LogWorkOrderActivity::class
        );

        // Manufacturing Domain - Material Requisition Event Listeners
        Event::listen(
            \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged::class,
            \App\Infrastructure\Listeners\Manufacturing\LogMaterialRequisitionActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionApproved::class,
            \App\Infrastructure\Listeners\Manufacturing\LogMaterialRequisitionActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionIssued::class,
            \App\Infrastructure\Listeners\Manufacturing\LogMaterialRequisitionActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionCancelled::class,
            \App\Infrastructure\Listeners\Manufacturing\LogMaterialRequisitionActivity::class
        );

        // Sales Domain - Sales Return Event Listeners
        Event::listen(
            \App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged::class,
            \App\Infrastructure\Listeners\Sales\LogSalesReturnActivity::class
        );

        Event::listen(
            \App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted::class,
            \App\Infrastructure\Listeners\Sales\LogSalesReturnActivity::class
        );

        Event::listen(
            \App\Domain\Sales\SalesReturns\Events\SalesReturnApproved::class,
            \App\Infrastructure\Listeners\Sales\LogSalesReturnActivity::class
        );

        Event::listen(
            \App\Domain\Sales\SalesReturns\Events\SalesReturnRejected::class,
            \App\Infrastructure\Listeners\Sales\LogSalesReturnActivity::class
        );

        Event::listen(
            \App\Domain\Sales\SalesReturns\Events\SalesReturnCompleted::class,
            \App\Infrastructure\Listeners\Sales\LogSalesReturnActivity::class
        );

        Event::listen(
            \App\Domain\Sales\SalesReturns\Events\SalesReturnCancelled::class,
            \App\Infrastructure\Listeners\Sales\LogSalesReturnActivity::class
        );

        // Projects Domain - Project Event Listeners
        Event::listen(
            \App\Domain\Projects\Events\ProjectStatusChanged::class,
            \App\Infrastructure\Listeners\Projects\LogProjectActivity::class
        );

        Event::listen(
            \App\Domain\Projects\Events\ProjectStarted::class,
            \App\Infrastructure\Listeners\Projects\LogProjectActivity::class
        );

        Event::listen(
            \App\Domain\Projects\Events\ProjectOnHold::class,
            \App\Infrastructure\Listeners\Projects\LogProjectActivity::class
        );

        Event::listen(
            \App\Domain\Projects\Events\ProjectResumed::class,
            \App\Infrastructure\Listeners\Projects\LogProjectActivity::class
        );

        Event::listen(
            \App\Domain\Projects\Events\ProjectCompleted::class,
            \App\Infrastructure\Listeners\Projects\LogProjectActivity::class
        );

        Event::listen(
            \App\Domain\Projects\Events\ProjectCancelled::class,
            \App\Infrastructure\Listeners\Projects\LogProjectActivity::class
        );

        // Manufacturing Domain - Subcontractor Work Order Event Listeners
        Event::listen(
            \App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStatusChanged::class,
            \App\Infrastructure\Listeners\Manufacturing\LogSubcontractorWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderAssigned::class,
            \App\Infrastructure\Listeners\Manufacturing\LogSubcontractorWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStarted::class,
            \App\Infrastructure\Listeners\Manufacturing\LogSubcontractorWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCompleted::class,
            \App\Infrastructure\Listeners\Manufacturing\LogSubcontractorWorkOrderActivity::class
        );

        Event::listen(
            \App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCancelled::class,
            \App\Infrastructure\Listeners\Manufacturing\LogSubcontractorWorkOrderActivity::class
        );
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
