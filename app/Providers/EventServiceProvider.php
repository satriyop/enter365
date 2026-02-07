<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event service provider with auto-discovery and subscriber registration.
 *
 * Events are automatically discovered from listener directories.
 * Subscribers consolidate related event handlers by domain.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The subscribers to register.
     *
     * @var array<class-string>
     */
    protected $subscribe = [
        // Sales Domain
        \App\Listeners\Sales\InvoiceEventSubscriber::class,
        \App\Listeners\Sales\QuotationEventSubscriber::class,
        \App\Listeners\Sales\SalesReturnEventSubscriber::class,
        \App\Listeners\Sales\DeliveryOrderEventSubscriber::class,

        // Purchasing Domain
        \App\Listeners\Purchasing\BillEventSubscriber::class,
        \App\Listeners\Purchasing\PurchaseOrderEventSubscriber::class,
        \App\Listeners\Purchasing\GoodsReceiptNoteEventSubscriber::class,
        \App\Listeners\Purchasing\PurchaseReturnEventSubscriber::class,

        // Manufacturing Domain
        \App\Listeners\Manufacturing\WorkOrderEventSubscriber::class,
        \App\Listeners\Manufacturing\MaterialRequisitionEventSubscriber::class,
        \App\Listeners\Manufacturing\SubcontractorWorkOrderEventSubscriber::class,

        // Projects Domain
        \App\Listeners\Projects\ProjectEventSubscriber::class,
        \App\Listeners\Projects\TaskEventSubscriber::class,

        // Inventory Domain
        \App\Listeners\Inventory\InventoryEventSubscriber::class,
        \App\Listeners\Inventory\StockOpnameEventSubscriber::class,

        // Accounting Domain
        \App\Listeners\Accounting\FiscalPeriodEventSubscriber::class,

        // Tax Domain
        \App\Listeners\Tax\NsfpEventSubscriber::class,
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path('Infrastructure/Listeners'),
            $this->app->path('Listeners'),
        ];
    }
}
